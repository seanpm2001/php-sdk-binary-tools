<?php

namespace SDK;

use SDK\Build\Dependency\Fetcher;
use SDK\Cache;
use SDK\Exception;

class Config
{
	/* Config variables. */
	protected static $depsHost = 'windows.php.net';
	protected static $depsPort = 80;
	protected static $depsBaseUri = "/downloads/php-sdk/deps";

	/* protected static $sdkNugetFeedUrl = "http://127.0.0.1/sdk/nuget"; */

	protected static $knownBranches = array ();

	/* Helper props and methods. */
	protected static $currentBranchName = NULL;
	protected static $currentArchName = NULL;
	protected static $currentCrtName = NULL;
	protected static $currentStabilityName = NULL;
	protected static $depsLocalPath = NULL;

	public static function getDepsHost() : string
	{/*{{{*/
		return self::$depsHost;
	}/*}}}*/

	public static function getDepsPort() : string
	{/*{{{*/
		return self::$depsPort;
	}/*}}}*/

	public static function getDepsBaseUri() : string
	{/*{{{*/
		return self::$depsBaseUri;
	}/*}}}*/

	public static function setCurrentArchName(string $arch = NULL) : bool
	{/*{{{*/
		if (NULL === $arch) {
			/* XXX this might be not true for other compilers! */
			passthru("where cl.exe >nul", $status);
			if (!$status) {
				exec("cl.exe /? 2>&1", $a, $status);
				if (!$status) {
					if (preg_match(",x64,", $a[0])) {
						self::$currentArchName = "x64";
						return true;
					} else {
						self::$currentArchName = "x86";
						return true;
					}
				}
			}

			return false;
		}

		self::$currentArchName = $arch;

		return true;
	}	/*}}}*/

	public static function getCurrentArchName() : ?string
	{/*{{{*/
		return self::$currentArchName;
	}	/*}}}*/

	public static function setCurrentCrtName(string $crt = NULL) : bool
	{/*{{{*/
		if (!$crt) {
			$all_branches = Config::getKnownBranches();

			if (!isset($all_branches[Config::getCurrentBranchName()])) {
				throw new Exception("Couldn't find any configuration for branch '" . Config::getCurrentBranchName() . "'");
			}

			$branch = $all_branches[Config::getCurrentBranchName()];
			if (count($branch) > 1) {
				throw new Exception("Multiple CRTs are available for this branch, please choose one from " . implode(",", array_keys($branch)));
			} else {
				self::$currentCrtName = array_keys($branch)[0];
				return true;
			}
			return false;
		}

		self::$currentCrtName = $crt;

		return true;
	}	/*}}}*/

	public static function getCurrentCrtName() : ?string
	{/*{{{*/
		return self::$currentCrtName;
	}	/*}}}*/

	public static function setCurrentStabilityName(string $stability) : void
	{/*{{{*/
		self::$currentStabilityName = $stability;
	}	/*}}}*/

	public static function getCurrentStabilityName() : ?string
	{/*{{{*/
		return self::$currentStabilityName;
	}	/*}}}*/

	public static function getKnownBranches() : array
	{/*{{{*/
		if (empty(self::$knownBranches)) {
			$cache_file = "known_branches.txt";
			$cache = new Cache(self::getDepsLocalPath());
			$fetcher = new Fetcher(self::$depsHost, self::$depsPort);

			$tmp = $fetcher->getByUri(self::$depsBaseUri . "/series/");
			if (false !== $tmp) {
				$data = array();
				if (preg_match_all(",/packages-(.+)-(vc\d+)-(x86|x64)-(stable|staging)\.txt,U", $tmp, $m, PREG_SET_ORDER)) {
					foreach ($m as $b) {
						if (!isset($data[$b[1]])) {
							$data[$b[1]] = array();
						}

						$data[$b[1]][$b[2]][] = array("arch" => $b[3], "stability" => $b[4]);
					}

					$cache->cachecontent($cache_file, json_encode($data, JSON_PRETTY_PRINT), true);
				}
			} else {
				/* It might be ok to use cached branches list, if a fetch failed. */
				$tmp = $cache->getCachedContent($cache_file, true);
				if (NULL == $tmp) {
					throw new Exception("No cached branches list found");
				}
				$data = json_decode($tmp, true);
			}

			if (!is_array($data) || empty($data)) {
				throw new Exception("Failed to fetch supported branches");
			}
			self::$knownBranches = $data;
		}

		return self::$knownBranches;
	}/*}}}*/

	public static function setCurrentBranchName(string $name = NULL) : bool
	{/*{{{*/
		if (!array_key_exists($name, self::getKnownBranches())) {
		//	throw new Exception("Unsupported branch '$name'");
		}

		if (!$name) {
			/* Try to figure out the branch. For now it only works if CWD is in php-src. */
			$fl = "main/php_version.h";
			if (file_exists($fl)) {
				$s = file_get_contents($fl);
				$major = $minor = NULL;

				if (preg_match(",PHP_MAJOR_VERSION (\d+),", $s, $m)) {
					$major = $m[1];
				}
				if (preg_match(",PHP_MINOR_VERSION (\d+),", $s, $m)) {
					$minor = $m[1];
				}

				if (is_numeric($major) && is_numeric($minor)) {
					self::$currentBranchName = "$major.$minor";
					return true;
				}
			}
			return false;
		}

		self::$currentBranchName = $name;

		return true; 
	}/*}}}*/

	public static function getCurrentBranchName() : ?string
	{/*{{{*/
		return self::$currentBranchName;
	}/*}}}*/

	public static function getCurrentBranchData() : array
	{/*{{{*/
		$ret = array();
		$branches = self::getKnownBranches();

		if (!array_key_exists(self::$currentBranchName, $branches)) {
			throw new Exception("Unknown branch '" . self::$currentBranchName . "'");
		}

		$cur_crt = Config::getCurrentCrtName();
		if (count($branches[self::$currentBranchName]) > 1) {
			if (NULL === $cur_crt) {
				throw new Exception("More than one CRT is available for branch '" . self::$currentBranchName . "', pass one explicitly.");
			}

			$cur_crt_usable = false;
			foreach (array_keys($branches[self::$currentBranchName]) as $crt) {
				if ($cur_crt == $crt) {
					$cur_crt_usable = true;
					break;
				}
			}
			if (!$cur_crt_usable) {
				throw new Exception("The passed CRT '$cur_crt' doesn't match any availbale for branch '" . self::$currentBranchName . "'");
			}
			$data = $branches[self::$currentBranchName][$cur_crt];
		} else {
			/* Evaluate CRTs, to avoid ambiquity. */
			list($crt, $data) = each($branches[self::$currentBranchName]);
			if ($crt != $cur_crt) {
				throw new Exception("The passed CRT '$cur_crt' doesn't match any availbale for branch '" . self::$currentBranchName . "'");
			}
		}

		$ret["name"] = self::$currentBranchName;
		$ret["crt"] = $crt;

		/* Last step, filter by arch and stability. */
		foreach ($data as $d) {
			if (self::getCurrentArchName() == $d["arch"]) {
				if (self::getCurrentStabilityName() == $d["stability"]) {
					$ret["arch"] = $d["arch"];
					$ret["stability"] = $d["stability"];
				}
			}
		}

		if (!$ret["stability"]) {
			throw new Exception("Failed to find config with stability '" . self::getCurrentStabilityName() . "'");
		}
		if (!$ret["crt"]) {
			throw new Exception("Failed to find config with arch '" . self::getCurrentArchName() . "'");
		}

		return $ret; 
	}/*}}}*/

	public static function getSdkNugetFeedUrl() : string
	{/*{{{*/
		return self::$sdkNugetFeedUrl;
	}/*}}}*/

	public static function getSdkPath() : string
	{/*{{{*/
		$path = getenv("PHP_SDK_ROOT_PATH");

		if (!$path) {
			throw new Exception("PHP_SDK_ROOT_PATH isn't set!");
		}

		$path = realpath($path);
		if (!file_exists($path)) {
			throw new Exception("The path '$path' is non existent.");
		}

		return $path;
	}/*}}}*/

	public static function getSdkVersion() : string
	{/*{{{*/
		$path = self::getSdkPath() . DIRECTORY_SEPARATOR . "VERSION";

		if (!file_exists($path)) {
			throw new Exception("Couldn't find the SDK version file.");
		}

		return file_get_contents($path);
	}/*}}}*/

	public static function getDepsLocalPath() : ?string
	{/*{{{*/
		return self::$depsLocalPath;
	}/*}}}*/

	public static function setDepsLocalPath(string $path = NULL) : bool
	{/*{{{*/
		if (!$path) {
			if (file_exists("../deps")) {
				self::$depsLocalPath = realpath("../deps");
				return true;
			} else if (file_exists("main/php_version.h")) {
				/* Deps dir might not exist. */
				self::$depsLocalPath = realpath("..") . DIRECTORY_SEPARATOR . "deps";
				return true;
			}
			return false;
		}

		self::$depsLocalPath = $path;

		return true;
	}/*}}}*/

	public static function getCacheDir() : string
	{/*{{{*/
		$path = self::getSdkPath() . DIRECTORY_SEPARATOR . ".cache";

		if (!file_exists($path)) {
			if (!mkdir($path)) {
				throw new Exception("Failed to create '$path'");
			}
		}

		return $path;
	}/*}}}*/

	public static function getTmpDir() : string
	{/*{{{*/
		$path = self::getSdkPath() . DIRECTORY_SEPARATOR . ".tmp";

		if (!file_exists($path)) {
			if (!mkdir($path)) {
				throw new Exception("Failed to create '$path'");
			}
		}

		return $path;
	}/*}}}*/
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
