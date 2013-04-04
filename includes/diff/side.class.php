<?php


// Side : a string for wdiff

class Side
{
    var $position;
    var $cursor;
    var $content;
    var $character;
    var $directive;
    var $argument;
    var $length;

    function Side($content)
    {
        $this->content = $content;
        $this->position = 0;
        $this->cursor = 0;
        $this->directive = '';
        $this->argument = array ();
        $this->length = strlen($this->content);
        $this->character = substr($this->content, 0, 1);

    }

    function getposition()
    {
        return $this->position;
    }

    function getcharacter()
    {
        return $this->character;
    }

    function getdirective()
    {
        return $this->directive;
    }

    function getargument()
    {
        return $this->argument;
    }

    function nextchar()
    {
        $this->cursor++;
        $this->character = substr($this->content, $this->cursor, 1);
    }

    function copy_until_ordinal($ordinal, & $out)
    {
        while ($this->position < $ordinal)
        {
            $this->copy_whitespace($out);
            $this->copy_word($out);
        }
    }

    function skip_until_ordinal($ordinal)
    {
        while ($this->position < $ordinal)
        {
            $this->skip_whitespace();
            $this->skip_word();
        }
    }

    function split_file_into_words(& $out)
    {
        while (!$this->isend())
        {
            $this->skip_whitespace();
            if ($this->isend())
            {
                break;
            }
            $this->copy_word($out);
            $out .= "\n";
        }
    }

    function init()
    {
        $this->position = 0;
        $this->cursor = 0;
        $this->directive = '';
        $this->argument = array ();
        $this->character = substr($this->content, 0, 1);
    }

    function isspace($char)
    {
        if (preg_match('/[[:space:]]/', $char))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function isdigit($char)
    {
        if (preg_match('/[[:digit:]]/', $char))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function isend()
    {
        if (($this->cursor) >= ($this->length))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    function copy_whitespace(& $out)
    {
        while (!$this->isend() && $this->isspace($this->character))
        {
            $out .= $this->character;
            $this->nextchar();
        }
    }

    function skip_whitespace()
    {
        while (!$this->isend() && $this->isspace($this->character))
        {
            $this->nextchar();
        }
    }

    function skip_line()
    {
        while (!$this->isend() && !$this->isdigit($this->character))
        {
            while (!$this->isend() && $this->character != "\n")
                $this->nextchar();
            if ($this->character == "\n")
                $this->nextchar();
        }
    }

    function copy_word(& $out)
    {
        while (!$this->isend() && !($this->isspace($this->character)))
        {
            $out .= $this->character;
            $this->nextchar();
        }
        $this->position++;
    }

    function skip_word()
    {

        while (!$this->isend() && !($this->isspace($this->character)))
        {
            $this->nextchar();
        }
        $this->position++;
    }

    function decode_directive_line()
    {

        $value = 0;
        $state = 0;
        $error = 0;

        while (!$error && $state < 4)
        {
            if ($this->isdigit($this->character))
            {
                $value = 0;
                while ($this->isdigit($this->character))
                {
                    $value = 10 * $value + $this->character - '0';
                    $this->nextchar();
                }
            }
            else
                if ($state != 1 && $state != 3)
                    $error = 1;

            /* Assign the proper value.  */

            $this->argument[$state] = $value;

            /* Skip the following character.  */

            switch ($state)
            {
                case 0 :
                case 2 :
                    if ($this->character == ',')
                        $this->nextchar();
                    break;

                case 1 :
                    if ($this->character == 'a' || $this->character == 'd' || $this->character == 'c')
                    {
                        $this->directive = $this->character;
                        $this->nextchar();
                    }
                    else
                        $error = 1;
                    break;

                case 3 :
                    if ($this->character != "\n")
                        $error = 1;
                    break;
            }
            $state++;
        }

        /* Complete reading of the line and return success value.  */

        while ((!$this->isend()) && ($this->character != "\n"))
        {
            $this->nextchar();
        }
        if ($this->character == "\n")
            $this->nextchar();

        return !$error;
    }

}
?>