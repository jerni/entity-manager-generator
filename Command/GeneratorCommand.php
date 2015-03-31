<?php

namespace Jse\EMGeneratorBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\DomCrawler\Crawler;

class GeneratorCommand extends ContainerAwareCommand
{
	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('generate:entity:manager')
			->setDescription('Generate Entity manager')
		;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$dialog = $this->getHelperSet()->get('dialog');
		$bundle = $dialog->askAndValidate(
			$output,
			'Please enter the name of the bundle: ',
			function ($answer) {
				if($answer){
					if ('Bundle' !== substr($answer, -6)) {
						throw new \RuntimeException(
							'The name of the bundle should be suffixed with \'Bundle\''
						);
					}
				
					$bundles = $this->getContainer()->getParameter('kernel.bundles');
					$bundleNames = array_keys($bundles);
					if(in_array($answer, $bundleNames)){
						$bundleDir = $this->getContainer()->get('kernel')->locateResource('@'.$answer);
						$namespace = str_replace('\\'.$answer,'',$bundles[$answer]);
                        $answer = str_replace('\\', '_', $namespace);
						$this->enterEntity($bundleDir, $namespace, $answer);
					} else {
						throw new \RuntimeException(
							'The Bundle does not exists.'
						);
					}
					die();
				} else {
					return true;
				}
			},
			false
		);
	}
	
	protected function enterEntity($bundleDir, $namespace, $bundlename){
		$dialog = $this->getHelperSet()->get('dialog');
		$output = new ConsoleOutput();
		$paths = array(
						'entityPath' => $bundleDir.'Entity/',
						'bundleNamespace' => $namespace,
						'bundleName' => $bundlename,
						);
		$bundle = $dialog->askAndValidate(
			$output,
			'Please enter the name of the entity [exit]: ',
			function ($answer) use ($paths) {
                $fs = new Filesystem();
				if($answer){
                    $interfacePath = $paths['entityPath'].'/Interfaces';
					$ManagerPath = $paths['entityPath'].'/Managers';
                
                    $interfacefile = $interfacePath.'/'.$answer.'Interface.php';
                    $interfacemanagerfile = $interfacePath.'/'.$answer.'ManagerInterface.php';
                    $entitymanagerfile = $ManagerPath.'/'.$answer.'Manager.php';
                    $managerservicefile = $paths['entityPath'].'/../Resources/config/entity_manager.xml';
					if($answer == 'exit'){
                        $toreplace = array('Bundle', '_');
                        $replacewith = array('', '');
                        $extension = str_replace($toreplace, $replacewith, $paths['bundleName']);
                        if($fs->exists($managerservicefile)  != false){
                            echo 'Load "entity_manager.xml" in bundle extension ('.$extension.'Extension.php)';
                        }
						return true;
					}
                    
                    if($fs->exists($interfacefile)  != false or
                        $fs->exists($interfacemanagerfile)  != false or
                        $fs->exists($entitymanagerfile)  != false){
                        throw new \RuntimeException(
							'Entity Manager File(s) already exists.'
						);
                    }
                    
					$finder = new Finder();
					$finder->files()->name('*.php');
					$entityFound = false;
					foreach ($finder->in($paths['entityPath']) as $file) {
						if($file->getFilename() == $answer.'.php'){
							$entityFound  = true;
							break;
						}
					}
					
					if($entityFound){
						if($fs->exists($interfacePath)  == false){
							$fs->mkdir($interfacePath);
						}
						
						if($fs->exists($ManagerPath)  == false){
							$fs->mkdir($ManagerPath);
						}
						
						$interface = file_get_contents(dirname(__FILE__).'/Patterns/Interface.txt');
						$interfacemanager = file_get_contents(dirname(__FILE__).'/Patterns/InterfaceManager.txt');
						$entitymanager = file_get_contents(dirname(__FILE__).'/Patterns/EntityManager.txt');
						if($fs->exists($managerservicefile)){
							$servicefile = file_get_contents($managerservicefile);
							$serviceid = strtolower($paths['bundleName']).'.'.strtolower($answer).'.entity.manager';
							$services = simplexml_load_file($managerservicefile);
							foreach($services->services as $service){
								foreach($service->service as $s){
									if($s['id'] == $serviceid){
										throw new \RuntimeException(
											'The Entity Manager Service already exists.'
										);
									}
								}
							}
						} else {
							$servicefile = file_get_contents(dirname(__FILE__).'/Patterns/servicefile.txt');
						}
						
						$parameters = file_get_contents(dirname(__FILE__).'/Patterns/parameters.txt');
						$service = file_get_contents(dirname(__FILE__).'/Patterns/service.txt');
						
						$bundlepath = $paths['bundleNamespace'];
						
						$fs->touch($interfacefile);
						$interfacePattern = str_replace(array('{bundlepath}', '{entityname}'), array($bundlepath, $answer), $interface);
						$fs->dumpFile($interfacefile, $interfacePattern);
						
						$fs->touch($interfacemanagerfile);
						$interfacemanagerPattern = str_replace(array('{bundlepath}', '{entityname}', '{entitynamevar}'), array($bundlepath, $answer, strtolower($answer)), $interfacemanager);
						$fs->dumpFile($interfacemanagerfile, $interfacemanagerPattern);
						
						$fs->touch($entitymanagerfile);
						$entitymanagerPattern = str_replace(array('{bundlepath}', '{entityname}', '{entitynamevar}'), array($bundlepath, $answer, strtolower($answer)), $entitymanager);
						$fs->dumpFile($entitymanagerfile, $entitymanagerPattern);
						
						//create service
						$fs->touch($managerservicefile);
						$entityname = $bundlepath.'\Entity\\'.$answer;
						$entityManager = $bundlepath.'\Entity\Managers\\'.$answer.'Manager';
						$parameterPattern = str_replace(
														array('{bundlename}', '{entitymanager}','{entityname}', '{entitynamevar}'), 
														array(strtolower($paths['bundleName']), $entityManager, $entityname, strtolower($answer)), 
														$parameters
														);
						$servicePattern = str_replace(
														array('{bundlename}', '{entitynamevar}'), 
														array(strtolower($paths['bundleName']), strtolower($answer)), 
														$service
														);
														
						$serviceFile = str_replace(
													array('<!--params-->', '<!--service-->'), 
													array($parameterPattern, $servicePattern), 
													$servicefile
													);
													
						$fs->dumpFile($managerservicefile, $serviceFile);
						
						throw new \RuntimeException(
							$answer.' Entity manager created.'
						);
					} else {
						throw new \RuntimeException(
							'The Entity does not exists.'
						);
					}
				}
				
				return true;
				
			},
			false,
			'exit'
		);
	}
}