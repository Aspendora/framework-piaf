<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Reload extends Command {
	protected function configure(){
		$this->FreePBXConf = \FreePBX::Config();
		$this->setName('reload')
		->setAliases(array('r'))
		->setDescription('Reload Configs')
		->setDefinition(array(
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$args = $input->getArgument('args');
		$result = do_reload();
		print_r($result);
		if ($result['status'] != true) {
			$output->writeln("Error(s) have occured, the following is the retrieve_conf output:");
			$retrieve_array = explode('<br/>',$result['retrieve_conf']);
			foreach ($retrieve_array as $line) {
				$line = preg_replace('#<br\s*/?>#i','', $line);
				$output->writeln($line);
			};
		} else {
			$output->writeln($result['message']);
		}
	}
}
