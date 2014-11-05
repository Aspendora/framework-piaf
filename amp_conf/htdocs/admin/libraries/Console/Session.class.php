<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Session extends Command {
	protected function configure(){
		$this->setName('session')
		->setAliases(array('s'))
		->setDescription('Manage Session')
		->setDefinition(array(
			new InputOption('list', 'l', InputOption::VALUE_NONE, 'List all sessions'),
			new InputOption('destroy', 'd', InputOption::VALUE_REQUIRED, 'Destroy Session'),
			new InputOption('killall', 'k', InputOption::VALUE_NONE, 'Destroy all sessions'),
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		if($input->getOption('list')){
			$sessions = scandir(session_save_path());
			foreach($sessions as $session){
				$filename = session_save_path() . '/' . $session;
				if(is_file($filename)){
					$id = substr($session,5);
					$output->writeln($id);
				}
			}
		}
		if($input->getOption('destroy')){
			$arg = $input->getOption('destroy');
			$filename = session_save_path() . '/sess_' . $arg;
			if(is_file($filename)){
				$output->writeln('Destroying session');
				unlink($filename);
			}
		}
		if($input->getOption('killall')){
			$sessions = scandir(session_save_path());
			foreach($sessions as $session){
				$filename = session_save_path() . '/' . $session;
				if(is_file($filename)){
					$output->writeln('Destroying ' . $session);
					unlink($filename);
				}
			}
		}
	}
}
