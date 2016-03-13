# PHP Dumper. Fork of Yii2 VarDumper
## Installation

Installation with Composer

Add in composer.json
~~~
    "require": {
        ...
        "kfosoft/php-var-dumper":"*"
    }
~~~

Well done!

## Example call
~~~
    \kfosoft\debug\Dumper::dump($var, 10, 'string');
	\kfosoft\debug\Dumper::dump($var, 10, 'highlight');
	\kfosoft\debug\Dumper::dump($var, 10, 'json');
~~~

Enjoy, guys!
