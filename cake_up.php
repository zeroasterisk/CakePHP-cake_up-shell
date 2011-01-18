<?php
/**
* This is just a scraps shell, helping me migrate a site from cake1.1 to cake1.3
* @author Alan@zeroasterisk.com
* @license MIT http://www.opensource.org/licenses/mit-license.php
*/
App::Import('Core', array('File', 'Folder'));
class CakeUpShell extends Shell{
	var $uses = array();

	function main(){
		$this->help();
	}
	function help() {
		$this->out("{$this->shell} Shell: HELP");
		$this->hr();
		$this->out("This shell is intended to ease upgrading from a cakephp 1.1 application to a cake 1.2 or 1.3+");
		$this->out("This must be run AFTER upgrading the cake folder to 1.2+");
		$this->out();
		$this->out("cake {$this->shell} views					renames .thtml to .ctp");
		$this->out();
		$this->out("cake {$this->shell} code 					looks for cakephp 1.1 code");
		$this->out("cake {$this->shell} code replace			automatically fixes known/easy 1.1 code replacements");
		$this->out("                          			WARNING: alters your site code");
		$this->out("                          			WARNING: be sure you've got a backup");
		$this->out();
		$this->out("note: the code find/replace functionality is based on some simple case regexes...");
		$this->out("      They are not going to work for everyone.");
		$this->out("      If you want to improve them for your needs, great, submit a pull request in github.");
		$this->out();
	}

	/**
	* This triggers the view_rename process
	*/
	function views() {
		$this->view_rename(APP . 'views');
		print_r($this->view_rename);
	}

	/**
	* Rename the files in the application
	*/
	function view_rename($file) {
		if (!file_exists($file) && file_exists(APP . views . DS . $file)) {
			$file = APP . views . DS . $file;
		}
		if (!file_exists($file)) {
			$this->out("Error: unable to find file to rename [{$file}]");
			return false;
		}
		if (is_file($file)) {
			if (strpos($file, '.thtml')!==false) {
				$this->view_rename['renamed'][] = $file;
				return rename($file, str_replace('.thtml', '.ctp', $file));
			} else {
				$this->view_rename['skipped'][] = $file;
				return true;
			}
		} elseif (is_dir($file)) {
			$this->view_rename['dirs'][] = $file;
			$folder = new Folder($file);
			$contents = $folder->read();
			foreach ( $contents as $i => $_files ) {
				foreach ( $_files as $_file ) {
					$this->view_rename($file . DS . $_file);
				}
			}
		}
	}

	/**
	* Look through the code of all of the files of interest in the application
	*/
	function code() {
		$code_issue_replace = false;
		if (in_array('replace', $this->args)) {
			$code_issue_replace = true;
		}
		$files = $this->files();
		foreach ( $files as $file ) {
			$code = file_get_contents($file);
			$issues = $this->code_issue_find($code);
			if (!empty($issues)) {
				if ($code_issue_replace) {
					$this->code_issue_replace($file, $code);
				} else {
					$this->out($file);
					print_r($issues);
				}
			}
		}
	}
	/**
	* Looks for known issues in the code of a page
	*/
	function code_issue_find($code) {
		$issues = array();
		if (preg_match('/DEBUG/', $code)) {
			// http://book.cakephp.org/view/577/Configure
			$issues[] = "configure::read('debug') constant found, need to switch to configure::read('debug')";
		}
		if (preg_match('/->renderElement\(/', $code)) {
			// http://book.cakephp.org/view/577/Configure
			$issues[] = "->renderElement() needs to change to ->element()";
		}
		if (preg_match('/->del\(/', $code)) {
			// http://book.cakephp.org/view/1561/Migrating-from-CakePHP-1-2-to-1-3
			$issues[] = "->del() needs to change to ->delete()";
		}
		if (preg_match('/->getReferrer\(/', $code)) {
			// http://book.cakephp.org/view/1561/Migrating-from-CakePHP-1-2-to-1-3
			$issues[] = "->getReferrer() needs to change to ->getReferer()";
		}
		if (preg_match('/->(mkdir|mv|ls|cp|rm)\(/', $code)) {
			// http://book.cakephp.org/view/1561/Migrating-from-CakePHP-1-2-to-1-3
			$issues[] = "->(mkdir|mv|ls|cp|rm) needs to change to ->(create|move|read|copy|delete) Folder Methods";
		}
		if (preg_match('/\$(this->)?(H|h)tml->(input|hidden|value|label|checkbox)\(/i', $code, $matches)) {
			// http://book.cakephp.org/view/578/HTML-Helper-to-Form-Helper
			$issues[] = "Html helper migrated to Form helper";
		}
		if (preg_match('/->generateList\(/i', $code, $matches)) {
			// http://book.cakephp.org/view/580/Model-generateList
			$issues[] = "generateList() needs to be migrated to find('list', array())";
		}
		if (preg_match('/(VALID_EMAIL|VALID_NOT_EMPTY|VALID_NUMBER|PEAR|INFLECTIONS|CIPHER_SEED)/i', $code, $matches)) {
			// http://book.cakephp.org/view/1561/Migrating-from-CakePHP-1-2-to-1-3
			$issues[] = "Old Validation constants need to be updated (cakephp 1.3) ".json_encode($matches);
		}
		return $issues;
	}
	/**
	* Looks for known "fixable" issues in the code of a page
	* then it fixes them {preg_replace()} and saves the file
	* WARNING: this is potentially destructive... be afraid!
	*/
	function code_issue_replace($file, $code) {
		$code = preg_replace('/DEBUG/', 'configure::read(\'debug\')', $code);
		$code = preg_replace('/->renderElement\(/', '->element(', $code);
		$code = preg_replace('/->del\(/', '->delete(', $code);
		$code = preg_replace('/->getReferrer\(/', '->getReferer(', $code);
		$code = preg_replace('/->mkdir\(/', '->create(', $code);
		$code = preg_replace('/->mv\(/', '->move(', $code);
		$code = preg_replace('/->ls\(/', '->read(', $code);
		$code = preg_replace('/->cp\(/', '->copy(', $code);
		$code = preg_replace('/->rm\(/', '->delete(', $code);
		$code = preg_replace('/\$(this->)?(H|h)tml->(input|hidden|value|label|checkbox)\(/i', '$this->Form->$3(', $code);
		$code = preg_replace('/->generateList\(\)/i', '->find(\'list\')', $code);
		$code = preg_replace('/->generateList\((null),\s*(null),\s*(null),\s*[\'"]\{n\}\.([\.\_\\$-\>a-zA-Z]+)[\'"],\s*[\'"]\{n\}\.([\.\_\\$-\>a-zA-Z]+)[\'"]\)/i',
			'->find(\'list\', array(\'fields\' => array(\'$4\', \'$5\')))', $code);
		$code = preg_replace('/->generateList\((null),\s*([\\\'\\"\.\_\\$-\>a-zA-Z0-9\s]+),\s*(null),\s*[\'"]\{n\}\.([\.\_\\$-\>a-zA-Z]+)[\'"],\s*[\'"]\{n\}\.([\.\_\\$-\>a-zA-Z]+)[\'"]\)/i',
			'->find(\'list\', array(\'order\' => $2, \'fields\' => array(\'$4\', \'$5\')))', $code);
		$code = preg_replace('/->generateList\((null),\s*([\\\'\\"\.\_\\$-\>a-zA-Z0-9\s]+),\s*([\\\'\\"0-9a-z]+),\s*[\'"]\{n\}\.([\.\_\\$-\>a-zA-Z]+)[\'"],\s*[\'"]\{n\}\.([\.\_\\$-\>a-zA-Z]+)[\'"]\)/i',
			'->find(\'list\', array(\'order\' => $2, \'limit\' => $3, \'fields\' => array(\'$4\', \'$5\')))', $code);
		$code = preg_replace('/->generateList\(([\\\'\\"\.\_\\$-\>\=a-zA-Z0-9\(\)\s]+),\s*([\\\'\\"\.\_\\$-\>a-zA-Z0-9\s]+),\s*([\\\'\\"0-9a-z]+),\s*[\'"]\{n\}\.([\.\_\\$-\>a-zA-Z]+)[\'"],\s*[\'"]\{n\}\.([\.\_\\$-\>a-zA-Z]+)[\'"]\)/i',
			'->find(\'list\', array(\'conditions\' => $1, \'order\' => $2, \'limit\' => $3, \'fields\' => array(\'$4\', \'$5\')))', $code);
		file_put_contents($file, $code);
	}

	/**
	* Collect all files we want to look at in the application
	*/
	function files($file = null) {
		if (empty($file)) {
			$file = APP;
		} elseif (strpos($file, 'tmp')!==false || strpos($file, 'webroot')!==false ||  strpos($file, 'plugins')!==false || strpos($file, 'config')!==false || strpos($file, '/cake/')!==false) {
			return null;
		}
		if (!isset($this->files)) {
			$this->files = array();
		}
		if (is_file($file)) {
			if ((strpos($file, '.php')===false && strpos($file, '.ctp')===false) || strpos($file, 'cake_up')!==false) {
				return null;
			}
			$this->files[] = $file;
		} elseif (is_dir($file)) {
			$folder = new Folder($file);
			$contents = $folder->read();
			foreach ( $contents as $i => $_files ) {
				foreach ( $_files as $_file ) {
					$this->files(str_replace(DS.DS, DS, $file . DS . $_file));
				}
			}
		}
		return $this->files;
	}
}
?>
