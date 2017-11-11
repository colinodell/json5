<?php

/*
 * This file is part of the colinodell/json5 package.
 *
 * (c) Colin O'Dell <colinodell@gmail.com>
 *
 * Based on the official JSON5 implementation for JavaScript (https://github.com/json5/json5)
 *  - (c) 2012-2016 Aseem Kishore and others (https://github.com/json5/json5/contributors)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ColinODell\Json5;

final class Json5Decoder
{
    const REGEX_WHITESPACE = '/[ \t\r\n\v\f\xA0\x{FEFF}]/u';

    private $json;

    private $at = 0;

    private $lineNumber = 1;

    private $columnNumber = 1;

    private $ch;

    private $associative = false;

    private $maxDepth = 512;

    private $castBigIntToString = false;

    private $depth = 1;

    private $length;

    private $lineCache;

    /**
     * Private constructor.
     *
     * @param string $json
     * @param bool   $associative
     * @param int    $depth
     * @param bool   $castBigIntToString
     */
    private function __construct($json, $associative = false, $depth = 512, $castBigIntToString = false)
    {
        $this->json = $json;
        $this->associative = $associative;
        $this->maxDepth = $depth;
        $this->castBigIntToString = $castBigIntToString;

        $this->length = mb_strlen($json, 'utf-8');

        $this->ch = $this->charAt(0);
    }

    /**
     * Takes a JSON encoded string and converts it into a PHP variable.
     *
     * The parameters exactly match PHP's json_decode() function - see
     * http://php.net/manual/en/function.json-decode.php for more information.
     *
     * @param string $source      The JSON string being decoded.
     * @param bool   $associative When TRUE, returned objects will be converted into associative arrays.
     * @param int    $depth       User specified recursion depth.
     * @param int    $options     Bitmask of JSON decode options.
     *
     * @return mixed
     */
    public static function decode($source, $associative = false, $depth = 512, $options = 0)
    {
        $associative = $associative || ($options & JSON_OBJECT_AS_ARRAY);
        $castBigIntToString = $options & JSON_BIGINT_AS_STRING;

        $decoder = new self((string)$source, $associative, $depth, $castBigIntToString);

        $result = $decoder->value();
        $decoder->white();
        if ($decoder->ch) {
            $decoder->throwSyntaxError('Syntax error');
        }

        return $result;
    }

    /**
     * @param int $at
     *
     * @return string|null
     */
    private function charAt($at)
    {
        if ($at < 0 || $at >= $this->length) {
            return null;
        }

        return mb_substr($this->json, $at, 1, 'utf-8');
    }

    /**
     * Parse the next character.
     *
     * If $c is given, the next char will only be parsed if the current
     * one matches $c.
     *
     * @param string|null $c
     *
     * @return null|string
     */
    private function next($c = null)
    {
        // If a c parameter is provided, verify that it matches the current character.
        if ($c !== null && $c !== $this->ch) {
            $this->throwSyntaxError(sprintf(
                'Expected %s instead of %s',
                self::renderChar($c),
                self::renderChar($this->ch)
            ));
        }

        // Get the next character. When there are no more characters,
        // return the empty string.
        if ($this->ch === "\n" || ($this->ch === "\r" && $this->peek() !== "\n")) {
            $this->at++;
            $this->lineNumber++;
            $this->columnNumber = 1;
        } else {
            $this->at++;
            $this->columnNumber++;
        }

        $this->ch = $this->charAt($this->at);

        return $this->ch;
    }

    /**
     * Get the next character without consuming it or
     * assigning it to the ch variable.
     *
     * @return mixed
     */
    private function peek()
    {
        return $this->charAt($this->at + 1);
    }

    /**
     * @return string
     */
    private function getLineRemainder()
    {
        // Line are separated by "\n" or "\r" without an "\n" next
        if ($this->lineCache === null) {
            $this->lineCache = preg_split('/\n|\r\n?/u', $this->json);
        }

        $line = $this->lineCache[$this->lineNumber - 1];

        return mb_substr($line, $this->columnNumber - 1, null, 'utf-8');
    }

    /**
     * Attempt to match a regular expression at the current position on the current line.
     *
     * This function will not match across multiple lines.
     *
     * @param string $regex
     *
     * @return string|null
     */
    private function match($regex)
    {
        $subject = $this->getLineRemainder();

        $matches = array();
        if (!preg_match($regex, $subject, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        // PREG_OFFSET_CAPTURE always returns the byte offset, not the char offset, which is annoying
        $offset = mb_strlen(mb_strcut($subject, 0, $matches[0][1], 'utf-8'), 'utf-8');

        // [0][0] contains the matched text
        // [0][1] contains the index of that match
        $advanceBy = $offset + mb_strlen($matches[0][0], 'utf-8');

        $this->at += $advanceBy;
        $this->columnNumber += $advanceBy;
        $this->ch = $this->charAt($this->at);

        return $matches[0][0];
    }

    /**
     * Parse an identifier.
     *
     * Normally, reserved words are disallowed here, but we
     * only use this for unquoted object keys, where reserved words are allowed,
     * so we don't check for those here. References:
     * - http://es5.github.com/#x7.6
     * - https://developer.mozilla.org/en/Core_JavaScript_1.5_Guide/Core_Language_Features#Variables
     * - http://docstore.mik.ua/orelly/webprog/jscript/ch02_07.htm
     */
    private function identifier()
    {
        // Be careful when editing this regex, there are a couple Unicode characters in between here -------------vv
        $match = $this->match('/^(?:[\$_\p{L}\p{Nl}]|\\\\u[0-9A-Fa-f]{4})(?:[\$_\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}‌‍]|\\\\u[0-9A-Fa-f]{4})*/u');

        if ($match === null) {
            $this->throwSyntaxError('Bad identifier as unquoted key');
        }

        // Un-escape escaped Unicode chars
        $unescaped = preg_replace_callback('/\\\\u([0-9A-Fa-f]{4})/', function ($m) {
            return Json5Decoder::fromCharCode($m[1]);
        }, $match);

        return $unescaped;
    }

    private function number()
    {
        $number = null;
        $sign = '';
        $string = '';
        $base = 10;

        if ($this->ch === '-' || $this->ch === '+') {
            $sign = $this->ch;
            $this->next($this->ch);
        }

        // support for Infinity
        if ($this->ch === 'I') {
            $number = $this->word();
            if ($number === null) {
                $this->throwSyntaxError('Unexpected word for number');
            }

            return ($sign === '-') ? -INF : INF;
        }

        // support for NaN
        if ($this->ch === 'N') {
            $number = $this->word();
            if ($number !== NAN) {
                $this->throwSyntaxError('expected word to be NaN');
            }

            // ignore sign as -NaN also is NaN
            return $number;
        }

        if ($this->ch === '0') {
            $string .= $this->ch;
            $this->next();
            if ($this->ch === 'x' || $this->ch === 'X') {
                $string .= $this->ch;
                $this->next();
                $base = 16;
            } elseif (is_numeric($this->ch)) {
                $this->throwSyntaxError('Octal literal');
            }
        }

        switch ($base) {
            case 10:
                if (($match = $this->match('/^\d*\.?\d*/')) !== null) {
                    $string .= $match;
                }
                if (($match = $this->match('/^[Ee][-+]?\d*/')) !== null) {
                    $string .= $match;
                }
                $number = $string;
                break;
            case 16:
                if (($match = $this->match('/^[A-Fa-f0-9]+/')) !== null) {
                    $string .= $match;
                    $number = hexdec($string);
                    break;
                }
                $this->throwSyntaxError('Bad hex number');
        }

        if ($sign === '-') {
            $number = -$number;
        }

        if (!is_numeric($number) || !is_finite($number)) {
            $this->throwSyntaxError('Bad number');
        }

        if ($this->castBigIntToString) {
            return $number;
        }

        // Adding 0 will automatically cast this to an int or float
        return $number + 0;
    }

    private function string()
    {
        if (!($this->ch === '"' || $this->ch === "'")) {
            $this->throwSyntaxError('Bad string');
        }

        $string = '';

        $delim = $this->ch;
        while ($this->next() !== null) {
            if ($this->ch === $delim) {
                $this->next();

                return $string;
            } elseif ($this->ch === '\\') {
                $this->next();
                if ($this->ch === 'u') {
                    $this->next();
                    $hex = $this->match('/^[A-Fa-f0-9]{4}/');
                    if ($hex === null) {
                        break;
                    }
                    $string .= self::fromCharCode($hex);
                } elseif ($this->ch === "\r") {
                    if ($this->peek() === "\n") {
                        $this->next();
                    }
                } elseif (($escapee = self::getEscapee($this->ch)) !== null) {
                    $string .= $escapee;
                } else {
                    break;
                }
            } elseif ($this->ch === "\n") {
                // unescaped newlines are invalid; see:
                // https://github.com/json5/json5/issues/24
                // @todo this feels special-cased; are there other invalid unescaped chars?
                break;
            } else {
                $string .= $this->ch;
            }
        }

        $this->throwSyntaxError('Bad string');
    }

    /**
     * Skip an inline comment, assuming this is one.
     *
     * The current character should be the second / character in the // pair that begins this inline comment.
     * To finish the inline comment, we look for a newline or the end of the text.
     */
    private function inlineComment()
    {
        if ($this->ch !== '/') {
            $this->throwSyntaxError('Not an inline comment');
        }

        do {
            $this->next();
            if ($this->ch === "\n" || $this->ch === "\r") {
                $this->next();

                return;
            }
        } while ($this->ch !== null);
    }

    /**
     * Skip a block comment, assuming this is one.
     *
     * The current character should be the * character in the /* pair that begins this block comment.
     * To finish the block comment, we look for an ending *​/ pair of characters,
     * but we also watch for the end of text before the comment is terminated.
     */
    private function blockComment()
    {
        if ($this->ch !== '*') {
            $this->throwSyntaxError('Not a block comment');
        }

        do {
            $this->next();
            while ($this->ch === '*') {
                $this->next('*');
                if ($this->ch === '/') {
                    $this->next('/');

                    return;
                }
            }
        } while ($this->ch);

        $this->throwSyntaxError('Unterminated block comment');
    }

    /**
     * Skip a comment, whether inline or block-level, assuming this is one.
     */
    private function comment()
    {
        // Comments always begin with a / character.
        if ($this->ch !== '/') {
            $this->throwSyntaxError('Not a comment');
        }

        $this->next('/');

        if ($this->ch === '/') {
            $this->inlineComment();
        } elseif ($this->ch === '*') {
            $this->blockComment();
        } else {
            $this->throwSyntaxError('Unrecognized comment');
        }
    }

    /**
     * Skip whitespace and comments.
     *
     * Note that we're detecting comments by only a single / character.
     * This works since regular expressions are not valid JSON(5), but this will
     * break if there are other valid values that begin with a / character!
     */
    private function white()
    {
        while ($this->ch) {
            if ($this->ch === '/') {
                $this->comment();
            } elseif (preg_match(self::REGEX_WHITESPACE, $this->ch) === 1) {
                $this->next();
            } else {
                return;
            }
        }
    }

    /**
     * Matches true, false, null, etc
     */
    private function word()
    {
        switch ($this->ch) {
            case 't':
                $this->next('t');
                $this->next('r');
                $this->next('u');
                $this->next('e');
                return true;
            case 'f':
                $this->next('f');
                $this->next('a');
                $this->next('l');
                $this->next('s');
                $this->next('e');
                return false;
            case 'n':
                $this->next('n');
                $this->next('u');
                $this->next('l');
                $this->next('l');
                return null;
            case 'I':
                $this->next('I');
                $this->next('n');
                $this->next('f');
                $this->next('i');
                $this->next('n');
                $this->next('i');
                $this->next('t');
                $this->next('y');
                return INF;
            case 'N':
                $this->next('N');
                $this->next('a');
                $this->next('N');
                return NAN;
        }

        $this->throwSyntaxError('Unexpected ' . self::renderChar($this->ch));
    }

    private function arr()
    {
        $arr = array();

        if ($this->ch === '[') {
            if (++$this->depth > $this->maxDepth) {
                $this->throwSyntaxError('Maximum stack depth exceeded');
            }

            $this->next('[');
            $this->white();
            while ($this->ch !== null) {
                if ($this->ch === ']') {
                    $this->next(']');
                    $this->depth--;
                    return $arr; // Potentially empty array
                }
                // ES5 allows omitting elements in arrays, e.g. [,] and
                // [,null]. We don't allow this in JSON5.
                if ($this->ch === ',') {
                    $this->throwSyntaxError('Missing array element');
                } else {
                    $arr[] = $this->value();
                }
                $this->white();
                // If there's no comma after this value, this needs to
                // be the end of the array.
                if ($this->ch !== ',') {
                    $this->next(']');
                    $this->depth--;
                    return $arr;
                }
                $this->next(',');
                $this->white();
            }
        }

        $this->throwSyntaxError('Bad array');
    }

    /**
     * Parse an object value
     */
    private function obj()
    {
        $key = null;
        $object = $this->associative ? array() : new \stdClass;

        if ($this->ch === '{') {
            if (++$this->depth > $this->maxDepth) {
                $this->throwSyntaxError('Maximum stack depth exceeded');
            }

            $this->next('{');
            $this->white();
            while ($this->ch) {
                if ($this->ch === '}') {
                    $this->next('}');
                    $this->depth--;
                    return $object; // Potentially empty object
                }

                // Keys can be unquoted. If they are, they need to be
                // valid JS identifiers.
                if ($this->ch === '"' || $this->ch === "'") {
                    $key = $this->string();
                } else {
                    $key = $this->identifier();
                }

                $this->white();
                $this->next(':');
                if ($this->associative) {
                    $object[$key] = $this->value();
                } else {
                    $object->{$key} = $this->value();
                }
                $this->white();
                // If there's no comma after this pair, this needs to be
                // the end of the object.
                if ($this->ch !== ',') {
                    $this->next('}');
                    $this->depth--;
                    return $object;
                }
                $this->next(',');
                $this->white();
            }
        }

        $this->throwSyntaxError('Bad object');
    }

    /**
     * Parse a JSON value.
     *
     * It could be an object, an array, a string, a number,
     * or a word.
     */
    private function value()
    {
        $this->white();
        switch ($this->ch) {
            case '{':
                return $this->obj();
            case '[':
                return $this->arr();
            case '"':
            case "'":
                return $this->string();
            case '-':
            case '+':
            case '.':
                return $this->number();
            default:
                return is_numeric($this->ch) ? $this->number() : $this->word();
        }
    }

    private function throwSyntaxError($message)
    {
        throw new SyntaxError($message, $this->at, $this->lineNumber, $this->columnNumber);
    }

    private static function renderChar($chr)
    {
        return $chr === null ? 'EOF' : "'" . $chr . "'";
    }

    /**
     * @param string $hex Hex code
     *
     * @return string Unicode character
     */
    private static function fromCharCode($hex)
    {
        return mb_convert_encoding('&#' . hexdec($hex) . ';', 'UTF-8', 'HTML-ENTITIES');
    }

    /**
     * @param string $ch
     *
     * @return string|null
     */
    private static function getEscapee($ch)
    {
        switch ($ch) {
            // @codingStandardsIgnoreStart
            case "'":  return "'";
            case '"':  return '"';
            case '\\': return '\\';
            case '/':  return '/';
            case "\n": return '';
            case 'b':  return '\b';
            case 'f':  return '\f';
            case 'n':  return '\n';
            case 'r':  return '\r';
            case 't':  return '\t';
            default:   return null;
            // @codingStandardsIgnoreEnd
        }
    }
}
