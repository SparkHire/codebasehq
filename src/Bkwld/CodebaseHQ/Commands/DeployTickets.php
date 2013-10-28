<?php namespace Bkwld\CodebaseHQ\Commands;

// Dependencies
use DateTime;
use DateTimeZone;
use Illuminate\Console\Command;
use SimpleXMLElement;
use Symfony\Component\Console\Input\InputOption;
use Bkwld\CodebaseHQ\Exception;

class DeployTickets extends Command {
	
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'codebasehq:deploy-tickets';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Pass git logs via STDIN and update all referenced tickets';

	/**
	 * The options
	 */
	protected function getOptions() {
		return array(
			array('server', 's', InputOption::VALUE_OPTIONAL, 'The name of the server enviornment being deployed to'),
		);
	}

	/**
	 * Inject dependencies
	 * @param Bkwld\CodebaseHQ\Request $request
	 * @param string $repo The name of the reo
	 */
	public function __construct($request, $repo) {
		$this->request = $request;
		$this->repo = $repo;
		parent::__construct();
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire() {
		
		// Require enviornment to be passed in
		$enviornment = $this->option('server');
		
		// Get info of person running the deploy
		$name = trim(`git config --get user.name`);
		$email = trim(`git config --get user.email`);
		
		// Loop through STDIN and find ticket references
		$commit= null;
		$deployed = array();
		while ($line = fgets(STDIN)) {
			
			// Check for a commit hash
			if (preg_match('#^commit (\w+)$#', $line, $match)) {
				$commit = $match[1];
			}
			
			// Check for a ticket and add to the deployed array
			if (preg_match_all('#\[(?:touch|complete):(\d+)\]#', $line, $matches)) {
				foreach($matches[1] as $ticket) {
					if (empty($deployed[$ticket])) $deployed[$ticket] = array();
					if (in_array($commit, $deployed[$ticket])) continue;
					$deployed[$ticket][] = $commit;
				}
			}
		}
		
		// Loop through those and creat ticket comments in codebase
		foreach($deployed as $ticket => $commits) {

			// Prepare message
			$date = new DateTime();
			$date->setTimezone(new DateTimeZone('America/Los_Angeles'));
			$date = $date->format('l, F jS \a\t g:i A T');
			$enviornment = $enviornment ? ", to **{$enviornment}**," : null;

			// Singular commits
			if (count($commits) === 1) {
				$message = "Note: [{$name}](mailto:{$email}) deployed{$enviornment} a commit that references this ticket on {$date}.\n\nThe commit was: {commit:{$this->repo}/{$commit}}";
			
			// Plural commits
			} else {
				$message = "Note: [{$name}](mailto:{$email}) deployed{$enviornment} commits that reference this ticket on {$date}.\n\nThe commits were:\n\n";
				foreach($commits as $commit) { $message .= "- {commit:{$this->repo}/{$commit}}\n"; }
			}

			// Create XML request
			$xml = new SimpleXMLElement('<ticket-note/>');
			$xml->addChild('content', $message);

			// Submit it.  Will throw exception on failure
			$this->request->call('POST', 'tickets/'.$ticket.'/notes', $xml->asXML());
		}
		
		// Ouptut status
		$this->info(count($deployed).' ticket(s) found and updated');
		
	}
	
}