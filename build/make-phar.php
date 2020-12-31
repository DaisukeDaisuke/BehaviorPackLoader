<?php

$file_phar = "BehaviorPackLoader.phar";
if(file_exists($file_phar)){
	echo "Phar file already exists, overwriting...";
	echo PHP_EOL;
	Phar::unlinkArchive($file_phar);
}

$files = [];
$dir = getcwd().DIRECTORY_SEPARATOR;

$exclusions = ["github",".gitignore","composer.json","composer.lock","build",".git"];

foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $path => $file){
	$bool = true;
	foreach($exclusions as $exclusion){
		if(strpos($path,$exclusion) !== false){
			$bool = false;
		}
	}

	if(!$bool){
		continue;
	}

	if($file->isFile() === false){
		continue;
	}
	$files[str_replace($dir,"",$path)] = $path;
}

echo "Compressing...".PHP_EOL;
$phar = new Phar($file_phar, 0);
$phar->startBuffering();
$phar->setSignatureAlgorithm(\Phar::SHA1);
$phar->buildFromIterator(new \ArrayIterator($files));
$phar->setStub("<?php __HALT_COMPILER(); ?>");

if(isset($argv[1])&&$argv[1] === "enableCompressAll"){
	$phar->compressFiles(Phar::GZ);
}else{
	foreach($phar as $file => $finfo){
		/** @var \PharFileInfo $finfo */
		if($finfo->getSize() > (1024 * 512)){
			$finfo->compress(\Phar::GZ);
		}
	}
}

$phar->stopBuffering();
echo "end.".PHP_EOL;
