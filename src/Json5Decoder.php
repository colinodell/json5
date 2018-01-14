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
    private $at = 0;

    private $lineNumber = 1;

    private $columnNumber = 1;

    private $ch;

    private $chArr;

    private $associative = false;

    private $maxDepth = 512;

    private $castBigIntToString = false;

    private $depth = 1;

    private $length;

    private $remainderCache;

    private $remainderCacheAt;

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
        $this->associative = $associative;
        $this->maxDepth = $depth;
        $this->castBigIntToString = $castBigIntToString;

        $this->length = mb_strlen($json, 'utf-8');

        $this->chArr = preg_split('//u', $json, null, PREG_SPLIT_NO_EMPTY);
        $this->ch = $this->charAt(0);

        $this->remainderCache = $json;
        $this->remainderCacheAt = 0;
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
        // Try parsing with json_decode first, since that's much faster
        // We only attempt this on PHP 7+ because 5.x doesn't parse some edge cases correctly
        if (PHP_VERSION_ID >= 700000) {
            $result = json_decode($source, $associative, $depth, $options);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $result;
            }
        }

        // Fall back to JSON5 if that fails
        $associative = $associative === true || ($associative === null && $options & JSON_OBJECT_AS_ARRAY);
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
        if ($at >= $this->length) {
            return null;
        }

        return $this->chArr[$at];
    }

    /**
     * Parse the next character.
     *
     * @return null|string
     */
    private function next()
    {
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
     * Parse the next character if it matches $c or fail.
     *
     * @param string $c
     *
     * @return string|null
     */
    private function nextOrFail($c)
    {
        if ($c !== $this->ch) {
            $this->throwSyntaxError(sprintf(
                'Expected %s instead of %s',
                self::renderChar($c),
                self::renderChar($this->ch)
            ));
        }

        return $this->next();
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
        $subject = $this->getRemainder();

        $matches = [];
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
        // @codingStandardsIgnoreStart
        // Be careful when editing this regex, there are a couple Unicode characters in between here -------------vv
        $match = $this->match('/^(?:[\$_\p{L}\p{Nl}]|\\\\u[0-9A-Fa-f]{4})(?:[\$_\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}‌‍]|\\\\u[0-9A-Fa-f]{4})*/u');
        // @codingStandardsIgnoreEnd

        if ($match === null) {
            $this->throwSyntaxError('Bad identifier as unquoted key');
        }

        // Un-escape escaped Unicode chars
        $unescaped = preg_replace_callback('/(?:\\\\u[0-9A-Fa-f]{4})+/', function ($m) {
            return json_decode('"'.$m[0].'"');
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
            $this->next();
        }

        // support for Infinity
        if ($this->ch === 'I') {
            $number = $this->word();

            return ($sign === '-') ? -INF : INF;
        }

        // support for NaN
        if ($this->ch === 'N') {
            $number = $this->word();

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
                if ((is_numeric($this->ch) || $this->ch === '.') && ($match = $this->match('/^\d*\.?\d*/')) !== null) {
                    $string .= $match;
                }
                if (($this->ch === 'E' || $this->ch === 'e') && ($match = $this->match('/^[Ee][-+]?\d*/')) !== null) {
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
        $string = '';

        $delim = $this->ch;
        $this->next();
        while ($this->ch !== null) {
            if ($this->ch === $delim) {
                $this->next();

                return $string;
            }

            if ($this->ch === '\\') {
                if ($this->peek() === 'u' && $unicodeEscaped = $this->match('/^(?:\\\\u[A-Fa-f0-9]{4})+/')) {
                    $string .= json_decode('"'.$unicodeEscaped.'"');
                    continue;
                }

                $this->next();
                if ($this->ch === "\r") {
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

            $this->next();
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
        do {
            $this->next();
            while ($this->ch === '*') {
                $this->nextOrFail('*');
                if ($this->ch === '/') {
                    $this->nextOrFail('/');

                    return;
                }
            }
        } while ($this->ch !== null);

        $this->throwSyntaxError('Unterminated block comment');
    }

    /**
     * Skip a comment, whether inline or block-level, assuming this is one.
     */
    private function comment()
    {
        // Comments always begin with a / character.
        $this->nextOrFail('/');

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
        while ($this->ch !== null) {
            if ($this->ch === '/') {
                $this->comment();
            } elseif (preg_match('/[ \t\r\n\v\f\xA0\x{FEFF}]/u', $this->ch) === 1) {
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
                $this->nextOrFail('t');
                $this->nextOrFail('r');
                $this->nextOrFail('u');
                $this->nextOrFail('e');
                return true;
            case 'f':
                $this->nextOrFail('f');
                $this->nextOrFail('a');
                $this->nextOrFail('l');
                $this->nextOrFail('s');
                $this->nextOrFail('e');
                return false;
            case 'n':
                $this->nextOrFail('n');
                $this->nextOrFail('u');
                $this->nextOrFail('l');
                $this->nextOrFail('l');
                return null;
            case 'I':
                $this->nextOrFail('I');
                $this->nextOrFail('n');
                $this->nextOrFail('f');
                $this->nextOrFail('i');
                $this->nextOrFail('n');
                $this->nextOrFail('i');
                $this->nextOrFail('t');
                $this->nextOrFail('y');
                return INF;
            case 'N':
                $this->nextOrFail('N');
                $this->nextOrFail('a');
                $this->nextOrFail('N');
                return NAN;
        }

        $this->throwSyntaxError('Unexpected ' . self::renderChar($this->ch));
    }

    private function arr()
    {
        $arr = [];

        if ($this->ch === '[') {
            if (++$this->depth > $this->maxDepth) {
                $this->throwSyntaxError('Maximum stack depth exceeded');
            }

            $this->nextOrFail('[');
            $this->white();
            while ($this->ch !== null) {
                if ($this->ch === ']') {
                    $this->nextOrFail(']');
                    $this->depth--;
                    return $arr; // Potentially empty array
                }
                // ES5 allows omitting elements in arrays, e.g. [,] and
                // [,null]. We don't allow this in JSON5.
                if ($this->ch === ',') {
                    $this->throwSyntaxError('Missing array element');
                }

                $arr[] = $this->value();

                $this->white();
                // If there's no comma after this value, this needs to
                // be the end of the array.
                if ($this->ch !== ',') {
                    $this->nextOrFail(']');
                    $this->depth--;
                    return $arr;
                }
                $this->nextOrFail(',');
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
        $object = $this->associative ? [] : new \stdClass;

        if ($this->ch === '{') {
            if (++$this->depth > $this->maxDepth) {
                $this->throwSyntaxError('Maximum stack depth exceeded');
            }

            $this->nextOrFail('{');
            $this->white();
            while ($this->ch) {
                if ($this->ch === '}') {
                    $this->nextOrFail('}');
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
                $this->nextOrFail(':');
                if ($this->associative) {
                    $object[$key] = $this->value();
                } else {
                    $object->{$key} = $this->value();
                }
                $this->white();
                // If there's no comma after this pair, this needs to be
                // the end of the object.
                if ($this->ch !== ',') {
                    $this->nextOrFail('}');
                    $this->depth--;
                    return $object;
                }
                $this->nextOrFail(',');
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
        throw new SyntaxError($message, $this->lineNumber, $this->columnNumber);
    }

    private static function renderChar($chr)
    {
        return $chr === null ? 'EOF' : "'" . $chr . "'";
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
            case 'b':  return chr(8);
            case 'f':  return "\f";
            case 'n':  return "\n";
            case 'r':  return "\r";
            case 't':  return "\t";
            default:   return null;
            // @codingStandardsIgnoreEnd
        }
    }

    /**
     * Returns everything from $this->at onwards.
     *
     * Utilizes a cache so we don't have to continuously parse through UTF-8
     * data that was earlier in the string which we don't even care about.
     *
     * @return string
     */
    private function getRemainder()
    {
        if ($this->remainderCacheAt === $this->at) {
            return $this->remainderCache;
        }

        $subject = mb_substr($this->remainderCache, $this->at - $this->remainderCacheAt);
        $this->remainderCache = $subject;
        $this->remainderCacheAt = $this->at;

        return $subject;
    }
}
