<?php
/**
 * Copyright (C) 2013-2015
 * Piotr Olaszewski <piotroo89@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
namespace WSDL\Parser;

use ReflectionClass;
use ReflectionProperty;
use WSDL\Types\Arrays;
use WSDL\Types\Object as ObjectType;

/**
 * WrapperParser
 *
 * @author Piotr Olaszewski <piotroo89@gmail.com>
 */
class WrapperParser
{
    private $_wrapperClass;
    /**
     * @var ComplexTypeParser[]
     */
    private $_complexTypes;

    /**
     * @desc Count of arrays in document
     * @var array
     */
    static private $arrayCnt = [];

    public function __construct($wrapperClass)
    {
        $this->_wrapperClass = new ReflectionClass($wrapperClass);
    }

    public function parse()
    {
        $publicFields = $this->_wrapperClass->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($publicFields as $field) {
            $this->_makeComplexType($field->getName(), $field->getDocComment());
        }
    }

    private function _makeComplexType($name, $docComment)
    {
        if (preg_match('#@type (\w*)\[\]#', $docComment, $matches)) {
            $type = $matches[1];
            $strategy = 'array';
        } else {
            preg_match('#@type (\w+)#', $docComment, $matches);
            if (isset($matches[1])) {
                $type = trim($matches[1]);
                $strategy = trim($matches[1]);
            } else {
                $type = 'void';
                $strategy = 'void';
            }
        }

        $optional = false;
        if (preg_match('#@optional#', $docComment, $matches)) {
            $optional = true;
        }

        switch ($strategy) {
            case 'object':
                $this->_complexTypes[] = new ObjectType($type, $name, $this->getComplexTypes(), $optional);
                break;
            case 'wrapper':
                $this->_complexTypes[] = $this->_createWrapperObject($type, $name, $docComment, $optional);
                break;
            case 'array':
                $this->_complexTypes[] = $this->_createArrayObject($type, $name, $docComment, $optional);
                break;
            default:
                $this->_complexTypes[] = new ComplexTypeParser($type, $name, $optional);
                break;
        }
    }

    private function _createWrapperObject($type, $name, $docComment, $optional = false)
    {
        $wrapper = $this->wrapper($type, $docComment);
        $object = null;
        if ($wrapper->getComplexTypes()) {
            $object = new ObjectType($type, $name, $wrapper->getComplexTypes(), $optional);
        }
        return new ObjectType($type, $name, $object, $optional);
    }

    private function _createArrayObject($type, $name, $docComment, $optional = false)
    {
        $object = null;
        if ($type == 'wrapper') {
            $complex = $this->wrapper($type, $docComment)->getComplexTypes();
            $object = new ObjectType($type, $name, $complex, $optional);
        } elseif ($this->isComplex($type)) {
            $complex = $this->getComplexTypes();
            $object = new ObjectType($type, $name, $complex, $optional);
        }
        if (!isset(self::$arrayCnt[$name])) {
            self::$arrayCnt[$name] = 0;
        }
        return new Arrays($type, $name, $object, $optional, self::$arrayCnt[$name]++);
    }

    public function getComplexTypes()
    {
        return $this->_complexTypes;
    }

    public function wrapper(&$type, $docComment)
    {
        if (!$this->isComplex($type)) {
            throw new WrapperParserException("This attribute is not complex type.");
        }
        preg_match('#@className=(.*?)(?:\s|$)#', $docComment, $matches);
        $className = $matches[1];
        $type = str_replace('\\', '', $className);
        $wrapperParser = new WrapperParser($className);
        $wrapperParser->parse();
        return $wrapperParser;
    }

    public function isComplex($type)
    {
        return in_array($type, array('object', 'wrapper'));
    }
}
