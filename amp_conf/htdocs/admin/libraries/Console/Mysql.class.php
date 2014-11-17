<?php
namespace FreePBX\Console\Command;
//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class Mysql extends Command {
	protected function configure(){
		$this->setName('m')
		->setAliases(array('mysql'))
		->setDescription('Run a mysql Query:')
		->setDefinition(array(
			new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}
	protected function execute(InputInterface $input, OutputInterface $output){
		$db = \FreePBX::Database();
		$arg = $input->getArgument('args');
		array_shift($arg);
		if ($arg) {
			//$sql = $db->prepare($arg[0]);
			$sql = $arg[0];
			$ob = $db->query($sql,\PDO::FETCH_ASSOC);
			if(!$ob){
				$output->writeln($db->errorInfo());
			}
			//if we get rows back from a query fetch them
			if($ob->rowCount())
				$gotRows = $ob->fetchAll();
			}
			
			//handle results if we got rows
			if($gotRows){
				$rows = array();
				foreach($gotRows as $row){
					array_push($rows, array_values($row));
				}
				$table = new Table($output);
				$table
					->setHeaders(array_keys($res[0]))
					->setRows($rows);
				$table->render();
			}
			
	}

}
