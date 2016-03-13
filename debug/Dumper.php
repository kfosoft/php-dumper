<?php
namespace kfosoft\debug;

 /**
 * Dumper provides concrete implementation for [[VarDumper]].
 * @package kfosoft\debug
 * @version 1.0.0
 * @copyright (c) 2015-2016 KFOSoftware Team <kfosoftware@gmail.com>
 */
class Dumper
{
	const AS_STRING = 'string';
	const AS_HIGHLIGHT = 'highlight';
	const AS_JSON = 'json';
	
    private static $_objects;
    private static $_output;
    private static $_depth;


    /**
     * Displays a variable.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param integer $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param string $type see consts of this class.
     */
    public static function dump($var, $depth = 10, $type = self::AS_STRING)
    {
		switch($type) {
			case self::AS_STRING :
				echo static::dumpAsString($var, $depth);
				break;
			case self::AS_HIGHLIGHT :
				echo static::dumpAsString($var, $depth, true);
				break;
			case self::AS_JSON :
				echo self::dumpAsJson($var, $depth);
				break;
			default: 
				throw new \Exception('Bad type. Use only string, highlight, json.');
		}
        
    }

    /**
     * Dumps a variable in terms of a string.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param integer $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param boolean $highlight whether the result should be syntax-highlighted
     * @return string the string representation of the variable
     */
    public static function dumpAsString($var, $depth = 10, $highlight = false)
    {
        self::$_output = '';
        self::$_objects = [];
        self::$_depth = $depth;
        self::dumpInternal($var, 0);
        if ($highlight) {
            $result = highlight_string("<?php\n" . self::$_output, true);
            self::$_output = preg_replace('/&lt;\\?php<br \\/>/', '', $result, 1);
        }

        return self::$_output;
    }
	
	/**
     * Dumps a variable in terms of a json.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param integer $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @return string the string representation of the variable
     */
    public static function dumpAsJson($var, $depth = 10)
    {
        self::$_output = '';
        self::$_objects = [];
        self::$_depth = $depth;

        return self::jsonEncode($var, 0);
    }

    /**
     * @param mixed $var variable to be dumped
     * @param integer $level depth level
     */
    private static function dumpInternal($var, $level)
    {
        switch (gettype($var)) {
            case 'boolean':
                self::$_output .= $var ? 'true' : 'false';
                break;
            case 'integer':
                self::$_output .= "$var";
                break;
            case 'double':
                self::$_output .= "$var";
                break;
            case 'string':
                self::$_output .= "'" . addslashes($var) . "'";
                break;
            case 'resource':
                self::$_output .= '{Resource}';
                break;
            case 'NULL':
                self::$_output .= 'null';
                break;
            case 'unknown type':
                self::$_output .= '{Unknown}';
                break;
            case 'array':
                if (self::$_depth <= $level) {
                    self::$_output .= '[...]';
                } elseif (empty($var)) {
                    self::$_output .= '[]';
                } else {
                    $keys = array_keys($var);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output .= '[';
                    foreach ($keys as $key) {
                        self::$_output .= "\n" . $spaces . '    ';
                        self::dumpInternal($key, 0);
                        self::$_output .= ' => ';
                        self::dumpInternal($var[$key], $level + 1);
                    }
                    self::$_output .= "\n" . $spaces . ']';
                }
                break;
            case 'object':
				if (($id = array_search($var, self::$_objects, true)) !== false) {
                    self::$_output .= get_class($var) . '#' . ($id + 1) . '(...)';
                } elseif (self::$_depth <= $level) {
                    self::$_output .= get_class($var) . '(...)';
                } else {
                    $id = array_push(self::$_objects, $var);
                    $className = get_class($var);
                    $spaces = str_repeat(' ', $level * 4);
					
					if($className === 'Closure'){
						self::$_output .= '{' . "{$className}#{$id}" . '}';
						continue;
					} else {
						self::$_output .= "$className#$id\n" . $spaces . '(';	
					}                    
					
					if (method_exists($var, '__debugInfo')) {
                        $dumpValues = $var->__debugInfo();
                        if (!is_array($dumpValues)) {
                            throw new \Exception('__debuginfo() must return an array');
                        }
                    } else {
                        $dumpValues = (array) $var;
                    }
                    foreach ($dumpValues as $key => $value) {
                        $keyDisplay = strtr(trim($key), "\0", ':');
                        self::$_output .= "\n" . $spaces . "    [$keyDisplay] => ";
                        self::dumpInternal($value, $level + 1);
                    }
                    self::$_output .= "\n" . $spaces . ')';
                }
                break;
        }
    }
	
	/**
	 * @param mixed $var variable to be dumped
     * @param integer $level depth level
	 */
	private static function jsonEncode($data, $level)
	{            
		if( is_array($data) || is_object($data) ) { 
			$islist = is_array($data) && ( empty($data) || array_keys($data) === range(0,count($data)-1) ); 
			
			if( $islist ) { 
				if (self::$_depth <= $level) {
					$json = '"Array:[...]"';
				} else {
					$closure = function ($data) use ($level) {
						return \kfosoft\debug\Dumper::jsonEncode($data, $level+1);
					};
					$json = '[' . implode(',', array_map($closure, $data) ) . ']'; 
				}
			} else { 
				$className = get_class($data);
				if($className === 'Closure'){
					$json = '"{Closure}"';
			    } elseif (self::$_depth <= $level) {
					$json = '"' . $className . ':{...}"';
				} else {
					$items = Array(); 
					$items[] = '"class": "' . $className . '"';
					foreach( (array)$data as $key => $value ) { 
						$key = strtr(trim($key), "\0", ':');
						$items[] = self::jsonEncode("$key", $level+1) . ':' . self::jsonEncode($value, $level+1); 
					}
					$json = '{' . implode(',', $items) . '}'; 	
				}
			} 
		} elseif( is_string($data) ) { 
			# Escape non-printable or Non-ASCII characters. 
			# I also put the \\ character first, as suggested in comments on the 'addclashes' page. 
			$string = '"' . addcslashes($data, "\\\"\n\r\t/" . chr(8) . chr(12)) . '"'; 
			$json   = ''; 
			$len    = strlen($string); 
			# Convert UTF-8 to Hexadecimal Codepoints. 
			for( $i = 0; $i < $len; $i++ ) { 
				
				$char = $string[$i]; 
				$c1 = ord($char); 
				
				# Single byte; 
				if( $c1 <128 ) { 
					$json .= ($c1 > 31) ? $char : sprintf("\\u%04x", $c1); 
					continue; 
				} 
				
				# Double byte 
				$c2 = ord($string[++$i]); 
				if ( ($c1 & 32) === 0 ) { 
					$json .= sprintf("\\u%04x", ($c1 - 192) * 64 + $c2 - 128); 
					continue; 
				} 
				
				# Triple 
				$c3 = ord($string[++$i]); 
				if( ($c1 & 16) === 0 ) { 
					$json .= sprintf("\\u%04x", (($c1 - 224) <<12) + (($c2 - 128) << 6) + ($c3 - 128)); 
					continue; 
				} 
					
				# Quadruple 
				$c4 = ord($string[++$i]); 
				if( ($c1 & 8 ) === 0 ) { 
					$u = (($c1 & 15) << 2) + (($c2>>4) & 3) - 1; 
				
					$w1 = (54<<10) + ($u<<6) + (($c2 & 15) << 2) + (($c3>>4) & 3); 
					$w2 = (55<<10) + (($c3 & 15)<<6) + ($c4-128); 
					$json .= sprintf("\\u%04x\\u%04x", $w1, $w2); 
				} 
			} 
		} elseif( is_resource($data) ) { 
			$json = '"{Resource}"';
		} else { 
			# int, floats, bools, null 
			$json = strtolower(var_export( $data, true )); 
		} 
		
		return $json;
	} 

    /**
     * Exports a variable as a string representation.
     *
     * The string is a valid PHP expression that can be evaluated by PHP parser
     * and the evaluation result will give back the variable value.
     *
     * This method is similar to `var_export()`. The main difference is that
     * it generates more compact string representation using short array syntax.
     *
     * It also handles objects by using the PHP functions serialize() and unserialize().
     *
     * PHP 5.4 or above is required to parse the exported value.
     *
     * @param mixed $var the variable to be exported.
     * @return string a string representation of the variable
     */
    public static function export($var)
    {
        self::$_output = '';
        self::exportInternal($var, 0);
        return self::$_output;
    }

    /**
     * @param mixed $var variable to be exported
     * @param integer $level depth level
     */
    private static function exportInternal($var, $level)
    {
        switch (gettype($var)) {
            case 'NULL':
                self::$_output .= 'null';
                break;
            case 'array':
                if (empty($var)) {
                    self::$_output .= '[]';
                } else {
                    $keys = array_keys($var);
                    $outputKeys = ($keys !== range(0, count($var) - 1));
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output .= '[';
                    foreach ($keys as $key) {
                        self::$_output .= "\n" . $spaces . '    ';
                        if ($outputKeys) {
                            self::exportInternal($key, 0);
                            self::$_output .= ' => ';
                        }
                        self::exportInternal($var[$key], $level + 1);
                        self::$_output .= ',';
                    }
                    self::$_output .= "\n" . $spaces . ']';
                }
                break;
            case 'object':
                if ($var instanceof \Closure) {
                    self::$_output .= self::exportClosure($var);
                } else {
                    try {
                        $output = 'unserialize(' . var_export(serialize($var), true) . ')';
                    } catch (\Exception $e) {
                        // serialize may fail, for example: if object contains a `\Closure` instance
                        // so we use a fallback
                        if ($var instanceof Arrayable) {
                            self::exportInternal($var->toArray(), $level);
                            return;
                        } elseif ($var instanceof \IteratorAggregate) {
                            $varAsArray = [];
                            foreach ($var as $key => $value) {
                                $varAsArray[$key] = $value;
                            }
                            self::exportInternal($varAsArray, $level);
                            return;
                        } elseif (method_exists($var, '__toString')) {
                            $output = var_export($var->__toString(), true);
                        } else {
                            $outputBackup = self::$_output;
                            $output = var_export(self::dumpAsString($var), true);
                            self::$_output = $outputBackup;
                        }
                    }
                    self::$_output .= $output;
                }
                break;
            default:
                self::$_output .= var_export($var, true);
        }
    }

    /**
     * Exports a [[Closure]] instance.
     * @param \Closure $closure closure instance.
     * @return string
     */
    private static function exportClosure(\Closure $closure)
    {
        $reflection = new \ReflectionFunction($closure);

        $fileName = $reflection->getFileName();
        $start = $reflection->getStartLine();
        $end = $reflection->getEndLine();

        if ($fileName === false || $start === false || $end === false) {
            return 'function() {/* Error: unable to determine Closure source */}';
        }

        --$start;

        $source = implode("\n", array_slice(file($fileName), $start, $end - $start));
        $tokens = token_get_all('<?php ' . $source);
        array_shift($tokens);

        $closureTokens = [];
        $pendingParenthesisCount = 0;
        foreach ($tokens as $token) {
            if (isset($token[0]) && $token[0] === T_FUNCTION) {
                $closureTokens[] = $token[1];
                continue;
            }
            if ($closureTokens !== []) {
                $closureTokens[] = isset($token[1]) ? $token[1] : $token;
                if ($token === '}') {
                    $pendingParenthesisCount--;
                    if ($pendingParenthesisCount === 0) {
                        break;
                    }
                } elseif ($token === '{') {
                    $pendingParenthesisCount++;
                }
            }
        }

        return implode('', $closureTokens);
    }
}
