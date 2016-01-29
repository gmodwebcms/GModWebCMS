<?php

class LangComponent extends Object {

  	public $components = array('Cookie');

    public $langFolder;

    public $languages;

    public $lang;

    public $mode = 'config'; // config ou cookie (pour choisir le language)

    function __construct() {
      // on set le dossier de langue
      $this->langFolder = ROOT.DS.'lang';

      // Maintenant on indexe tout les fichiers de langues
      $this->languages = $this->getLanguages();

      // on choisi le language et on indexe les messages
      $this->lang = $this->getLang();
    }

	  function shutdown(&$controller) {}
	  function beforeRender(&$controller) {}
  	function beforeRedirect() {}
	  function initialize(&$controller) {
		  $this->controller =& $controller;
		  $this->controller->set('Lang', $this);
	  }
    function startup(&$controller) {}

    public function getLanguages() {
      $languages_available = array();

      $dh  = opendir($this->langFolder);
			while (false !== ($filename = readdir($dh))) {
				if(explode('.', $filename)[1] == "json") {

          // on lis la config
          $fileContent = file_get_contents($this->langFolder.DS.$filename);
          $fileContent = json_decode($fileContent, true);

          if(!empty($fileContent) && $fileContent !== false) {

            if(isset($fileContent['INFORMATIONS']) && isset($fileContent['INFORMATIONS']['name']) && isset($fileContent['INFORMATIONS']['author']) && isset($fileContent['INFORMATIONS']['version']) && isset($fileContent['MESSAGES'])) {

              // on met tout ça dans l'array
              $languages_available[explode('.', $filename)[0]]['name'] = $fileContent['INFORMATIONS']['name'];
              $languages_available[explode('.', $filename)[0]]['author'] = $fileContent['INFORMATIONS']['author'];
              $languages_available[explode('.', $filename)[0]]['version'] = $fileContent['INFORMATIONS']['version'];
              $languages_available[explode('.', $filename)[0]]['path'] = explode('.', $filename)[0];
              $languages_available[explode('.', $filename)[0]]['fullpath'] = $this->langFolder.DS.$filename;

            } else {
              $this->log('Language file : '.$filename.' is not a valid lang format.');
            }

          } else {
            $this->log('Language file : '.$filename.' is not a valid JSON format.');
          }

				}
			}

      return $languages_available;
    }

    public function getLang($mode = false) {

      $mode = (!$mode) ? $this->mode : $mode;

      // Si c'est config dans les cookies
      if($mode == 'cookie') {
        if(isset($_COOKIE['language'])) {
  	    	$language = $_COOKIE['language'];
  		  } elseif(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
  			  $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
  			  $language = $language{0}.$language{1};
    		}
      } else { // config
        App::import('Component', 'Configuration');
    		$this->Configuration = new ConfigurationComponent();
    		$language = $this->Configuration->get('lang');
      }

      // Si la langue existe bien

      if(empty($language) || !isset($this->languages[$language])) {
        $language = 'fr_FR'; // sinon on met en français de base
      }

      $language = $this->languages[$language];

      $lang = file_get_contents($this->langFolder.DS.$language['path'].'.json');
			$language['messages'] = json_decode($lang, true)['MESSAGES'];

      App::import('Component', 'EyPlugin');
      $this->EyPlugin = new EyPluginComponent();

      $plugins = $this->EyPlugin->getPluginsActive();

      if(!empty($plugins)) {

        foreach ($plugins as $key => $value) {
          $name = $value->slug;

          if(file_exists($this->EyPlugin->pluginsFolder.DS.$name.DS.'lang'.DS.$language['path'].'.json')) {
            $language_file = file_get_contents($this->EyPlugin->pluginsFolder.DS.$name.DS.'lang'.DS.$language['path'].'.json');
            $language_file = json_decode($language_file, true);
          } elseif(file_exists($this->EyPlugin->pluginsFolder.DS.$name.DS.'lang'.DS.'fr_FR.json')) {
            $language_file = file_get_contents($this->EyPlugin->pluginsFolder.DS.$name.DS.'lang'.DS.'fr_FR.json');
            $language_file = json_decode($language_file, true);
          }

          $language['messages'] = array_merge($language['messages'], $language_file); // on le rajoute aux messages
        }

      }

      return $language;

    }

    public function get($msg) {

    	$language = $this->lang;

	  	if(isset($language['messages'][$msg])) { // et si le msg existe
			  return $language['messages'][$msg]; // je retourne le msg config
		  }

      return $msg; // le msg tel quel ou modifié
		}

    public function getAll() {

    	$language = $this->lang;

      $lang = file_get_contents($this->langFolder.DS.$language['path'].'.json');
			$messages['CMS'] = json_decode($lang, true)['MESSAGES'];

      App::import('Component', 'EyPlugin');
      $this->EyPlugin = new EyPluginComponent();

      $plugins = $this->EyPlugin->getPluginsActive();

      if(!empty($plugins)) {

        foreach ($plugins as $key => $value) {
          $name = $value->slug;

          if(file_exists($this->EyPlugin->pluginsFolder.DS.$name.DS.'lang'.DS.$language['path'].'.json')) {
            $language_file = file_get_contents($this->EyPlugin->pluginsFolder.DS.$name.DS.'lang'.DS.$language['path'].'.json');
            $language_file = json_decode($language_file, true);
          } elseif(file_exists($this->EyPlugin->pluginsFolder.DS.$name.DS.'lang'.DS.'fr_FR.json')) {
            $language_file = file_get_contents($this->EyPlugin->pluginsFolder.DS.$name.DS.'lang'.DS.'fr_FR.json');
            $language_file = json_decode($language_file, true);
          }

          $messages[$name] = $language_file; // on le rajoute aux messages
        }

      }

      return $messages; // le msg tel quel ou modifié
		}

    public function set($msg, $value) {

      $language = $this->lang;

      $lang = file_get_contents($this->langFolder.DS.$language['path'].'.json');
			$lang = json_decode($lang, true);

      $lang['MESSAGES'][$msg] = $value;

      $edit = json_encode($lang, JSON_PRETTY_PRINT);
      @file_put_contents($this->langFolder.DS.$language['path'].'.json', $edit);

    }

    public function setAll($data) {

    	$language = $this->getAll();

      $path = $this->lang['path'];

      foreach ($data as $key => $value) { // on parcours les données

        foreach ($language as $type => $messages) { // on parcours tout les types de messages par plugins/CMS.

          if(isset($messages[$key])) { // si c'est dans cette catégorie

            $language[$type][$key] = $value; // on set le message dans la variable

          }

        }

      }


      foreach ($language as $type => $messages) {

        if($type == "CMS") {

          $JSON['INFORMATIONS']['name'] = $this->lang['name'];
          $JSON['INFORMATIONS']['version'] = $this->lang['version'];
          $JSON['INFORMATIONS']['author'] = $this->lang['author'];

          foreach ($messages as $key => $value) {
            if($this->lang['messages'][$key] != $value) { // ca veut dire que on l'a update

              if(file_exists(ROOT.DS.'app'.DS.'tmp'.DS.'logs'.DS.'update'.DS.'lang'.DS.$path.'.log.json')) {
                $log = file_get_contents(ROOT.DS.'app'.DS.'tmp'.DS.'logs'.DS.'update'.DS.'lang'.DS.$path.'.log.json');
                $log = json_decode($log, true);
              } else {
                $log['update'] = array();
              }

              $log['update'][$key] = date('Y-m-d H:i:s');

              file_put_contents(ROOT.DS.'app'.DS.'tmp'.DS.'logs'.DS.'update'.DS.'lang'.DS.$path.'.log.json', json_encode($log, JSON_PRETTY_PRINT));

            }
          }

          $JSON['MESSAGES'] = $messages;

    			$fp = fopen($this->langFolder.DS.$path.'.json',"w+");
    			fwrite($fp, json_encode($JSON, JSON_PRETTY_PRINT));
    			fclose($fp);

        } else { // plugin

          if(file_exists($this->EyPlugin->pluginsFolder.DS.'lang'.DS.$path.'.json')) {

    				$fp = fopen($this->EyPlugin->pluginsFolder.DS.'lang'.DS.$path.'.json',"w+");
    				fwrite($fp, json_encode($messages, JSON_PRETTY_PRINT));
    				fclose($fp);

          }

        }

      }

    }

    /*
    Update le fichier de langue
    */

    public function update($JSON, $file) {

      if(!file_exists($file)) {

        $fileRessource = fopen(ROOT.DS.$file, 'w');
        fwrite($fileRessource, $JSON);
        fclose($fileRessource);

        return;

      } else {

        $fileContent = file_get_contents($filename);
        $fileContent = json_decode($fileContent, true);

        $newContent = $fileContent;

        $updatedContent = json_decode($JSON, true);

        $newContent['INFORMATIONS']['VERSION'] = $updatedContent['INFORMATIONS']['VERSION']; // on change la version

        $path = end(explode('/', $file));
        $path = explode('.', $path)[0];

        foreach ($fileContent['MESSAGES'] as $key => $value) { // on parcours les messages pour éventuellement les mettre à jours

          if(file_exists(ROOT.DS.'app'.DS.'tmp'.DS.'logs'.DS.'update'.DS.'lang'.DS.$path.'.log.json')) {
            $log = file_get_contents(ROOT.DS.'app'.DS.'tmp'.DS.'logs'.DS.'update'.DS.'lang'.DS.$path.'.log.json');
            $log = json_decode($log, true);
          } else {
            $log['update'] = array();
          }

          if(!isset($log['update'][$key])) { // si ca a pas était modifié par l'utilisateur
            $newContent['MESSAGES'][$key] = $updatedContent['MESSAGES'][$key]; // on met le nouveau pour cette clé
          }

        }

        foreach ($updatedContent['MESSAGES'] as $key => $value) { // on parcours les nouveaux messages pour eventuellement en ajouter
          if(!isset($fileContent['MESSAGES'][$key])) {
            $newContent['MESSAGES'][$key] = $value;
          }
        }

        $fileRessource = fopen(ROOT.DS.$file, 'w');
        fwrite($fileRessource, json_encode($newContent));
        fclose($fileRessource);

        return;

      }

    }

  	function banner_server($call) {
  		$language = $this->lang;
  		if(isset($language['messages']['BANNER_SERVER'])) {
  			$return = str_replace('{MOTD}', @$call['getMOTD'], $language['messages']['BANNER_SERVER']);
  			$return = str_replace('{VERSION}', @$call['getVersion'], $return);
  			$return = str_replace('{ONLINE}', @$call['getPlayerCount'], $return);
  			$return = str_replace('{ONLINE_LIMIT}', @$call['getPlayerMax'], $return);
  			return $return;
  		} else {
  			return 'BANNER_SERVER';
  		}
  	}

	  function date($date) {

  		$language = $this->lang;

  		if(isset($language['messages']['GLOBAL__FORMAT_DATE'])) { // si le format de la date est configuré je fais les actions suivantes
  			$date = explode(' ', $date); // j'explode les espaces pour séparé date & heure
  			$time = explode(':', $date['1']); // ensuite je sépare tout les chiffre de la date
  			$date = explode('-', $date['0']); // puis tout ceux de l'heure
  			$return = str_replace('{%day}', $date['2'], $language['messages']['GLOBAL__FORMAT_DATE']); // puis je remplace les variable de la config lang.php par les chiffres | Le jour
  			$return = str_replace('{%month}', $date['1'], $return); // puis je remplace les variable de la config lang.php par les chiffres | Le mois
  			$return = str_replace('{%year}', $date['0'], $return); // puis je remplace les variable de la config lang.php par les chiffres | L'année
  			$return = str_replace('{%minutes}', $time['1'], $return); // puis je remplace les variable de la config lang.php par les chiffres | Les minutes
  			$if = explode('|', $return); // ensuite j'explode pour savoir a quelle format je retourne l'heure
  			$if = explode('}', $if['1']); // 24h ou 12h
  			if($if['0'] == 12) { // donc si c'est 12h
  				if($time['0'] > 12) { // et que c'est plus de 12h donc l'après midi
  					if($time['0'] == 13) { // je remplace tout les chiffre par leur équivalent en 12h
  						$hour = '01';
  						$pm_or_am = 'PM'; // et je dis bien que c'est l'après-midi
  					} elseif($time['0'] == 14) {
  						$hour = '02';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 15) {
  						$hour = '03';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 16) {
  						$hour = '04';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 17) {
  						$hour = '05';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 18) {
  						$hour = '06';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 19) {
  						$hour = '07';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 20) {
  						$hour = '08';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 21) {
  						$hour = '09';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 22) {
  						$hour = '10';
  						$pm_or_am = 'PM';
  					} elseif($time['0'] == 23) {
  						$hour = '11';
  						$pm_or_am = 'PM';
  					}
  				} else { // sinon c'est que c'est le matin
  					$hour = $time['0']; // donc je laisse tel quel
  					$pm_or_am = 'AM';
  				}
  				$return = str_replace('{%hour|12}', $hour, $return); // je change donc les variables
  				$return = str_replace('{%PM_OR_AM}', $pm_or_am, $return);
  			} elseif($if['0'] == 24) { // et si c'est du 24h
  				$hour = $time['0']; // je laisse comme c'est en bdd
  				$return = str_replace('{%hour|24}', $hour, $return); // et je remplace
        } else {
          $return = 'ERROR'; // format inconnu
        }
  		} else { // sinon, si le message GLOBAL__FORMAT_DATE n'est pas configuré
  			$return = $date; // je laisse la date tel quel
  		}

  		return $return; // puis je retourne la date & l'heure
    }

}
?>
