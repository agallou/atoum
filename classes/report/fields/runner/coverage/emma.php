<?php

namespace mageekguy\atoum\report\fields\runner\coverage;

require_once __DIR__ . '/../../../../../constants.php';

use
	mageekguy\atoum,
	mageekguy\atoum\locale,
	mageekguy\atoum\report,
	mageekguy\atoum\template,
	mageekguy\atoum\exceptions,
	mageekguy\atoum\cli\prompt,
	mageekguy\atoum\cli\colorizer
;

class emma extends report\fields\runner\coverage\cli
{
	const htmlExtensionFile = '.emma';

	protected $adapter = null;
	protected $projectName = '';
	protected $destinationFile = null;

	public function __construct($projectName, $destinationFile, atoum\adapter $adapter = null)
	{
		$this
			->setDestinationFile($destinationFile)
			->setProjectName($projectName)
			->setAdapter($adapter ?: new atoum\adapter())
		;
	}

	public function setReflectionClassInjector(\closure $reflectionClassInjector)
	{
		$closure = new \reflectionMethod($reflectionClassInjector, '__invoke');

		if ($closure->getNumberOfParameters() != 1)
		{
			throw new exceptions\logic\invalidArgument('Reflection class injector must take one argument');
		}

		$this->reflectionClassInjector = $reflectionClassInjector;

		return $this;
	}

	public function getReflectionClass($class)
	{
		$reflectionClass = null;

		if ($this->reflectionClassInjector === null)
		{
			$reflectionClass = new \reflectionClass($class);
		}
		else
		{
			$reflectionClass = $this->reflectionClassInjector->__invoke($class);

			if ($reflectionClass instanceof \reflectionClass === false)
			{
				throw new exceptions\runtime\unexpectedValue('Reflection class injector must return a \reflectionClass instance');
			}
		}

		return $reflectionClass;
	}

	public function __toString()
	{
		$coverageClasses = $this->coverage->getClasses();
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$dom->formatOutput = true;
		$dom->appendChild($dom->createComment(' EMMA report, generated ' . date('r') . ' '));
		$dom->appendChild($report = $dom->createElement('report'));
		$stats = $dom->createElement('stats');

		$packages = $dom->createElement('packages');
		$classes  = $dom->createElement('classes');
		$methods  = $dom->createElement('methods');
		$srclines = $dom->createElement('srclines');
		$srcfiles = $dom->createElement('srcfiles');

		$classCount = count($coverageClasses);
		$classes->setAttribute('value', $classCount);

		$methodCount = 0;
		foreach ($this->coverage->getMethods() as $method)
		{
			$methodCount += count($method);
		}
		$methods->setAttribute('value', $methodCount);

		$stats->appendChild($packages);
		$stats->appendChild($classes);
		$stats->appendChild($methods);
		$stats->appendChild($srcfiles);
		$stats->appendChild($srclines);

		$report->appendChild($stats);

		$data = $dom->createElement('data');
		$all  = $dom->createElement('all');
		$all->setAttribute('name', 'all classes');
		$data->appendChild($all);
		$report->appendChild($data);

		$infos = array(
			'class' => array(
				'pourcent' => 0,
				'covered'  => $classCount,
				'total'    => $classCount,
			),
			'method' => array(
				'pourcent' => 0,
				'covered'  => $methodCount,
				'total'    => $methodCount,
			),
			'block'  => $this->getEmptyBlock(),
			'line'   => array(
				'pourcent' => 0,
				'covered'  => 0,
				'total'    => 0,
			),
		);
		$this->appendInfos($dom, $all, $infos);

		$package = $dom->createElement('package');
		$package->setAttribute('name', 'all');
		$this->appendInfos($dom, $package, $infos);

		foreach ($this->coverage->getMethods() as $className => $methods)
		{
			$srcfile = $dom->createElement('srcfile');
			$srcfile->setAttribute('name', $className);

			$classCoverageValue = $this->coverage->getValueForClass($className);

			$infos = array(
				'class'  => array(
					'pourcent' => round($classCoverageValue * 100, 2),
					'covered'  => 0,
					'total'    => 0,
				),
				'method' => array(
					'pourcent' => 0,
					'covered'  => 0,
					'total'    => 0,
				),
				'block'  => $this->getEmptyBlock(),
				'line'   => array(
					'pourcent' => 0,
					'covered'  => 0,
					'total'    => 0,
				),
			);
			$this->appendInfos($dom, $srcfile, $infos);

			$class = $dom->createElement('class');
			$class->setAttribute('name', $className);


			$package->appendChild($srcfile);
		}

		$all->appendChild($package);

		$this->getAdapter()->file_put_contents($this->getDestinationFile(), $dom->saveXml());
		return sprintf('file %s writed', $this->getDestinationFile(), ' writed') . PHP_EOL;
	}

	public function appendInfos(\DOMDocument $dom, \DOMElement $element, array $infos)
	{
		foreach ($infos as $name => $info)
		{
			$coverageClass = $dom->createElement('coverage');
			$coverageClass->setAttribute('type', $name . ', %');
			$coverageClass->setAttribute('value', sprintf(
				'%d%% (%d/%d)',
				$info['pourcent'],
				$info['covered'],
				$info['total']
			));
			$element->appendChild($coverageClass);
		}
	}

	protected function getEmptyBlock()
	{
		return array(
			'pourcent' => 0,
			'covered'  => 0,
			'total'    => 0,
		);
	}

	public function setAdapter(atoum\adapter $adapter)
	{
		$this->adapter = $adapter;

		return $this;
	}

	public function getAdapter()
	{
		return $this->adapter;
	}

	public function setDestinationFile($path)
	{
		$this->destinationFile = (string) $path;

		return $this;
	}

	public function getDestinationFile()
	{
		return $this->destinationFile;
	}

	public function setProjectName($projectName)
	{
		$this->projectName = (string) $projectName;

		return $this;
	}

	public function getProjectName()
	{
		return $this->projectName;
	}
}
