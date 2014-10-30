<?php
//Namespace should be FreePBX\Console\Command
namespace FreePBX\Console\Command;

//Symfony stuff all needed add these
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
//progress bar
use Symfony\Component\Console\Helper\ProgressBar;

class Start extends Command {
	protected function configure(){
		$this->setName('start')
			->setDescription('Start Asterisk and run other needed FreePBX commands')
			->setDefinition(array(
				new InputArgument('args', InputArgument::IS_ARRAY, null, null),));
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$output->writeln('Running FreePBX startup...');
		$output->writeln('');
		$output->writeln('Checking Asterisk Status...');
		$aststat = $this->asteriskProcess();
		if($aststat[0]){
			$output->writeln('Asterisk Seems to be running on PID: <info>'. $aststat[0] . '</info> and has been running for <info>' . $aststat[1]. '</info>');
			$output->writeln('<info>Not running Pre-Asterisk Hooks.</info>');
		}else{
			$output->writeln('Run Pre-Asterisk Hooks');
			$this->preAsteriskHooks($output);
			$output->writeln('');
			$this->startAsterisk($output);
			$progress = new ProgressBar($output, 100);
			$progress->start();
			$i = 0;
			while ($i++ < 3) {
			$progress->advance(33);
			sleep(1);
			}
			$aststat = $this->asteriskProcess(); 
			if($aststat[0]){
				$progress->finish();
				$output->writeln('');
				$output->writeln('Asterisk Started on <info>' . $aststat[0] . '</info>');
				$output->writeln('');
				$output->writeln('Running Post-Asterisk Scripts');
				$this->postAsteriskHooks($output);
			}
		}
	}
	private function asteriskProcess(){
		$ps = '/bin/env ps';
		$cmd = $ps . " -C asterisk --no-headers -o '%p|%t'";
		$stat = exec($cmd);
		return explode('|',$stat);
	}
	private function startAsterisk($output){
		$output->writeln('Starting Asterisk...');
		$astbin = '/bin/env safe_asterisk > /dev/null 2>&1 &';
		exec($astbin);
	}
	private function preAsteriskHooks($output){
		$output->writeln("HERE");
		\FreePBX::Hooks()->processHooks($output);
		return;
	}
	private function postAsteriskHooks($output){
		$output->writeln("THERE");
		\FreePBX::Hooks()->processHooks($output);
		return;
	}
}
