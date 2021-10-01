<?php
/*****************************************************************************
 *
 *		 Wiki Parser (beta, very quick implementation)
 *		 ---------------
 *
 *		 Class to parse wiki text in html
 *
 *    Copyright (C) 2010 De Smet Nicolas (<http://ndesmet.be>).
 *    All Rights Reserved
 *
 *    Very inspired by MediaWiki (which is under GPL2)
 *    http://www.mediawiki.org/wiki/MediaWiki
 *    /includes/parser/Parser.php
 *
 *
 *    This program is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU General Public License as published by
 *    the Free Software Foundation, either version 3 of the License, or
 *    (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU General Public License for more details.
 *
 *    You should have received a copy of the GNU General Public License
 *    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *****************************************************************************/

class zyfra_wiki_parser {
    
    var $mInPre;
    
    function __construct(){
        
    }
    
    function parse($text){
        $this->clear_state();
        //$text = $this->doTableStuff( $text );

        //$text = preg_replace( '/(^|\n)-----*/', '\\1<hr />', $text );

        //$text = $this->doDoubleUnderscore( $text );

        $text = $this->do_headings($text);
        $text = $this->do_all_quotes($text);
        //$text = $this->replaceInternalLinks( $text );
        //$text = $this->replaceExternalLinks( $text );

        # replaceInternalLinks may sometimes leave behind
        # absolute URLs, which have to be masked to hide them from replaceExternalLinks
        //$text = str_replace($this->mUniqPrefix.'NOPARSE', '', $text);

        //$text = $this->doMagicLinks( $text );
        //$text = $this->formatHeadings( $text, $origText, $isMain );

        $text = $this->do_block_levels($text);
        return $text;
    }
    
    function clear_state() {
        $this->mAutonumber = 0;
        $this->mLastSection = '';
        $this->mDTopen = false;
        $this->mIncludeCount = array();
        $this->mArgStack = false;
        $this->mInPre = false;
        $this->mLinkID = 0;
        $this->mRevisionTimestamp = $this->mRevisionId = null;
        $this->mVarCache = array();
        /**
         * Prefix for temporary replacement strings for the multipass parser.
         * \x07 should never appear in input as it's disallowed in XML.
         * Using it at the front also gives us a little extra robustness
         * since it shouldn't match when butted up against identifier-like
         * string constructs.
         *
         * Must not consist of all title characters, or else it will change
         * the behaviour of <nowiki> in a link.
         */
        #$this->mUniqPrefix = "\x07UNIQ" . Parser::getRandomString();
        # Changed to \x7f to allow XML double-parsing -- TS
        $this->mUniqPrefix = "\x7fUNIQ" . self::getRandomString();
    }

    function getRandomString() {
        return dechex(mt_rand(0, 0x7fffffff)) . dechex(mt_rand(0, 0x7fffffff));
    }

    // Parse header
    function do_headings($text) {
        for ($i = 6; $i >= 1; --$i){
            $h = str_repeat('=', $i);
            $text = preg_replace("/^$h(.+)$h\\s*$/m", "<h$i>\\1</h$i>", 
                                 $text );
        }
        return $text;
    }

    /**
     * Replace single quotes with HTML markup
     * @private
     * @return string the altered text
     */
    // Parse single quotes (italic, bold, ...)
    function do_all_quotes($text) {
        $outtext = '';
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $outtext .= $this->do_quotes($line) . "\n";
        }
        $outtext = substr($outtext, 0, -1);
        return $outtext;
    }

    // Helper for quote parser
    public function do_quotes($text) {
        $arr = preg_split("/(''+)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($arr) == 1)
            return $text;
        else
        {
            # First, do some preliminary work. This may shift some apostrophes from
            # being mark-up to being text. It also counts the number of occurrences
            # of bold and italics mark-ups.
            $i = 0;
            $numbold = 0;
            $numitalics = 0;
            foreach ( $arr as $r )
            {
                if ( ( $i % 2 ) == 1 )
                {
                    # If there are ever four apostrophes, assume the first is supposed to
                    # be text, and the remaining three constitute mark-up for bold text.
                    if ( strlen( $arr[$i] ) == 4 )
                    {
                        $arr[$i-1] .= "'";
                        $arr[$i] = "'''";
                    }
                    # If there are more than 5 apostrophes in a row, assume they're all
                    # text except for the last 5.
                    else if ( strlen( $arr[$i] ) > 5 )
                    {
                        $arr[$i-1] .= str_repeat( "'", strlen( $arr[$i] ) - 5 );
                        $arr[$i] = "'''''";
                    }
                    # Count the number of occurrences of bold and italics mark-ups.
                    # We are not counting sequences of five apostrophes.
                    if ( strlen( $arr[$i] ) == 2 )      { $numitalics++;             }
                    else if ( strlen( $arr[$i] ) == 3 ) { $numbold++;                }
                    else if ( strlen( $arr[$i] ) == 5 ) { $numitalics++; $numbold++; }
                }
                $i++;
            }

            # If there is an odd number of both bold and italics, it is likely
            # that one of the bold ones was meant to be an apostrophe followed
            # by italics. Which one we cannot know for certain, but it is more
            # likely to be one that has a single-letter word before it.
            if ( ( $numbold % 2 == 1 ) && ( $numitalics % 2 == 1 ) )
            {
                $i = 0;
                $firstsingleletterword = -1;
                $firstmultiletterword = -1;
                $firstspace = -1;
                foreach ( $arr as $r )
                {
                    if ( ( $i % 2 == 1 ) and ( strlen( $r ) == 3 ) )
                    {
                        $x1 = substr ($arr[$i-1], -1);
                        $x2 = substr ($arr[$i-1], -2, 1);
                        if ($x1 === ' ') {
                            if ($firstspace == -1) $firstspace = $i;
                        } else if ($x2 === ' ') {
                            if ($firstsingleletterword == -1) $firstsingleletterword = $i;
                        } else {
                            if ($firstmultiletterword == -1) $firstmultiletterword = $i;
                        }
                    }
                    $i++;
                }

                # If there is a single-letter word, use it!
                if ($firstsingleletterword > -1)
                {
                    $arr [ $firstsingleletterword ] = "''";
                    $arr [ $firstsingleletterword-1 ] .= "'";
                }
                # If not, but there's a multi-letter word, use that one.
                else if ($firstmultiletterword > -1)
                {
                    $arr [ $firstmultiletterword ] = "''";
                    $arr [ $firstmultiletterword-1 ] .= "'";
                }
                # ... otherwise use the first one that has neither.
                # (notice that it is possible for all three to be -1 if, for example,
                # there is only one pentuple-apostrophe in the line)
                else if ($firstspace > -1)
                {
                    $arr [ $firstspace ] = "''";
                    $arr [ $firstspace-1 ] .= "'";
                }
            }

            # Now let's actually convert our apostrophic mush to HTML!
            $output = '';
            $buffer = '';
            $state = '';
            $i = 0;
            foreach ($arr as $r)
            {
                if (($i % 2) == 0)
                {
                    if ($state === 'both')
                    $buffer .= $r;
                    else
                    $output .= $r;
                }
                else
                {
                    if (strlen ($r) == 2)
                    {
                        if ($state === 'i')
                        { $output .= '</i>'; $state = ''; }
                        else if ($state === 'bi')
                        { $output .= '</i>'; $state = 'b'; }
                        else if ($state === 'ib')
                        { $output .= '</b></i><b>'; $state = 'b'; }
                        else if ($state === 'both')
                        { $output .= '<b><i>'.$buffer.'</i>'; $state = 'b'; }
                        else # $state can be 'b' or ''
                        { $output .= '<i>'; $state .= 'i'; }
                    }
                    else if (strlen ($r) == 3)
                    {
                        if ($state === 'b')
                        { $output .= '</b>'; $state = ''; }
                        else if ($state === 'bi')
                        { $output .= '</i></b><i>'; $state = 'i'; }
                        else if ($state === 'ib')
                        { $output .= '</b>'; $state = 'i'; }
                        else if ($state === 'both')
                        { $output .= '<i><b>'.$buffer.'</b>'; $state = 'i'; }
                        else # $state can be 'i' or ''
                        { $output .= '<b>'; $state .= 'b'; }
                    }
                    else if (strlen ($r) == 5)
                    {
                        if ($state === 'b')
                        { $output .= '</b><i>'; $state = 'i'; }
                        else if ($state === 'i')
                        { $output .= '</i><b>'; $state = 'b'; }
                        else if ($state === 'bi')
                        { $output .= '</i></b>'; $state = ''; }
                        else if ($state === 'ib')
                        { $output .= '</b></i>'; $state = ''; }
                        else if ($state === 'both')
                        { $output .= '<i><b>'.$buffer.'</b></i>'; $state = ''; }
                        else # ($state == '')
                        { $buffer = ''; $state = 'both'; }
                    }
                }
                $i++;
            }
            # Now close all remaining tags.  Notice that the order is important.
            if ($state === 'b' || $state === 'ib')
            $output .= '</b>';
            if ($state === 'i' || $state === 'bi' || $state === 'ib')
            $output .= '</i>';
            if ($state === 'bi')
            $output .= '</b>';
            # There might be lonely ''''', so make sure we have a buffer
            if ($state === 'both' && $buffer)
            $output .= '<b><i>'.$buffer.'</i></b>';
            return $output;
        }
    }
    
    // Make lists from lines starting with ':', '*', '#', etc. (DBL)
    // @param $linestart bool whether or not this is at the start of a line.
    function do_block_levels($text, $linestart = true){
        # Parsing through the text line by line.  The main thing
        # happening here is handling of block-level elements p, pre,
        # and making lists from lines starting with * # : etc.
        #
        $textLines = explode("\n", $text);

        $lastPrefix = $output = '';
        $this->mDTopen = $inBlockElem = false;
        $prefixLength = 0;
        $paragraphStack = false;

        foreach ($textLines as $oLine){
            # Fix up $linestart
            if (!$linestart){
                $output .= $oLine;
                $linestart = true;
                continue;
            }
            // * = ul
            // # = ol
            // ; = dt
            // : = dd

            $lastPrefixLength = strlen($lastPrefix);
            $preCloseMatch = preg_match('/<\\/pre/i', $oLine);
            $preOpenMatch = preg_match('/<pre/i', $oLine);
            // If not in a <pre> element, scan for and figure out what prefixes are there.
            if (!$this->mInPre) {
                # Multiple prefixes may abut each other for nested lists.
                $prefixLength = strspn( $oLine, '*#:;' );
                $prefix = substr( $oLine, 0, $prefixLength );

                # eh?
                // ; and : are both from definition-lists, so they're equivalent
                //  for the purposes of determining whether or not we need to open/close
                //  elements.
                $prefix2 = str_replace( ';', ':', $prefix );
                $t = substr( $oLine, $prefixLength );
                $this->mInPre = (bool)$preOpenMatch;
            } else {
                # Don't interpret any other prefixes in preformatted text
                $prefixLength = 0;
                $prefix = $prefix2 = '';
                $t = $oLine;
            }

            # List generation
            if( $prefixLength && $lastPrefix === $prefix2 ) {
                # Same as the last item, so no need to deal with nesting or opening stuff
                $output .= $this->nextItem( substr( $prefix, -1 ) );
                $paragraphStack = false;

                if ( substr( $prefix, -1 ) === ';') {
                    # The one nasty exception: definition lists work like this:
                    # ; title : definition text
                    # So we check for : in the remainder text to split up the
                    # title and definition, without b0rking links.
                    $term = $t2 = '';
                    if ($this->findColonNoLinks($t, $term, $t2) !== false) {
                        $t = $t2;
                        $output .= $term . $this->nextItem( ':' );
                    }
                }
            } elseif( $prefixLength || $lastPrefixLength ) {
                // We need to open or close prefixes, or both.

                # Either open or close a level...
                $commonPrefixLength = $this->getCommon( $prefix, $lastPrefix );
                $paragraphStack = false;

                // Close all the prefixes which aren't shared.
                while( $commonPrefixLength < $lastPrefixLength ) {
                    $output .= $this->closeList( $lastPrefix[$lastPrefixLength-1] );
                    --$lastPrefixLength;
                }

                // Continue the current prefix if appropriate.
                if ( $prefixLength <= $commonPrefixLength && $commonPrefixLength > 0 ) {
                    $output .= $this->nextItem( $prefix[$commonPrefixLength-1] );
                }

                // Open prefixes where appropriate.
                while ( $prefixLength > $commonPrefixLength ) {
                    $char = substr( $prefix, $commonPrefixLength, 1 );
                    $output .= $this->openList( $char );

                    if ( ';' === $char ) {
                        # FIXME: This is dupe of code above
                        if ($this->findColonNoLinks($t, $term, $t2) !== false) {
                            $t = $t2;
                            $output .= $term . $this->nextItem( ':' );
                        }
                    }
                    ++$commonPrefixLength;
                }
                $lastPrefix = $prefix2;
            }

            // If we have no prefixes, go to paragraph mode.
            if( 0 == $prefixLength ) {
                # No prefix (not in list)--go to paragraph mode
                // XXX: use a stack for nestable elements like span, table and div
                $openmatch = preg_match('/(?:<table|<blockquote|<h1|<h2|<h3|<h4|<h5|<h6|<pre|<tr|<p|<ul|<ol|<li|<\\/tr|<\\/td|<\\/th)/iS', $t );
                $closematch = preg_match(
					'/(?:<\\/table|<\\/blockquote|<\\/h1|<\\/h2|<\\/h3|<\\/h4|<\\/h5|<\\/h6|'.
					'<td|<th|<\\/?div|<hr|<\\/pre|<\\/p|'.$this->mUniqPrefix.'-pre|<\\/li|<\\/ul|<\\/ol|<\\/?center)/iS', $t );
                if ( $openmatch or $closematch ) {
                    $paragraphStack = false;
                    # TODO bug 5718: paragraph closed
                    $output .= $this->closeParagraph();
                    if ( $preOpenMatch and !$preCloseMatch ) {
                        $this->mInPre = true;
                    }
                    if ( $closematch ) {
                        $inBlockElem = false;
                    } else {
                        $inBlockElem = true;
                    }
                } else if ( !$inBlockElem && !$this->mInPre ) {
                    if ( ' ' == substr( $t, 0, 1 ) and ( $this->mLastSection === 'pre' or trim($t) != '' ) ) {
                        // pre
                        if ($this->mLastSection !== 'pre') {
                            $paragraphStack = false;
                            $output .= $this->closeParagraph().'<pre>';
                            $this->mLastSection = 'pre';
                        }
                        $t = substr( $t, 1 );
                    } else {
                        // paragraph
                        if ( trim($t) == '' ) {
                            if ( $paragraphStack ) {
                                $output .= $paragraphStack.'<br />';
                                $paragraphStack = false;
                                $this->mLastSection = 'p';
                            } else {
                                if ($this->mLastSection !== 'p' ) {
                                    $output .= $this->closeParagraph();
                                    $this->mLastSection = '';
                                    $paragraphStack = '<p>';
                                } else {
                                    $paragraphStack = '</p><p>';
                                }
                            }
                        } else {
                            if ( $paragraphStack ) {
                                $output .= $paragraphStack;
                                $paragraphStack = false;
                                $this->mLastSection = 'p';
                            } else if ($this->mLastSection !== 'p') {
                                $output .= $this->closeParagraph().'<p>';
                                $this->mLastSection = 'p';
                            }
                        }
                    }
                }
            }
            // somewhere above we forget to get out of pre block (bug 785)
            if($preCloseMatch && $this->mInPre) {
                $this->mInPre = false;
            }
            if ($paragraphStack === false) {
                if (substr($output, -1) == "\n"){
                    $output .= '<br />';
                }
                $output .= $t."\n";
            }
        }
        while ( $prefixLength ) {
            $output .= $this->closeList( $prefix2[$prefixLength-1] );
            --$prefixLength;
        }
        if ( $this->mLastSection != '' ) {
            $output .= '</' . $this->mLastSection . '>';
            $this->mLastSection = '';
        }

        return $output;
    }
    
    //Used by doBlockLevels()
    function closeParagraph() {
        $result = '';
        if ( $this->mLastSection != '' ) {
            $result = '</' . $this->mLastSection  . ">\n";
        }
        $this->mInPre = false;
        $this->mLastSection = '';
        return $result;
    }
    # getCommon() returns the length of the longest common substring
    # of both arguments, starting at the beginning of both.
    #
    /* private */ function getCommon( $st1, $st2 ) {
    $fl = strlen( $st1 );
    $shorter = strlen( $st2 );
    if ( $fl < $shorter ) { $shorter = $fl; }

    for ( $i = 0; $i < $shorter; ++$i ) {
        if ( $st1[$i] != $st2[$i] ) { break; }
    }
    return $i;
    }
    # These next three functions open, continue, and close the list
    # element appropriate to the prefix character passed into them.
    #
    /* private */ function openList( $char ) {
    $result = $this->closeParagraph();

    if ( '*' === $char ) { $result .= '<ul><li>'; }
    elseif ( '#' === $char ) { $result .= '<ol><li>'; }
    elseif ( ':' === $char ) { $result .= '<dl><dd>'; }
    elseif ( ';' === $char ) {
        $result .= '<dl><dt>';
        $this->mDTopen = true;
    }
    else { $result = '<!-- ERR 1 -->'; }

    return $result;
    }

    /* private */ function nextItem( $char ) {
    if ( '*' === $char || '#' === $char ) { return '</li><li>'; }
    elseif ( ':' === $char || ';' === $char ) {
        $close = '</dd>';
        if ( $this->mDTopen ) { $close = '</dt>'; }
        if ( ';' === $char ) {
            $this->mDTopen = true;
            return $close . '<dt>';
        } else {
            $this->mDTopen = false;
            return $close . '<dd>';
        }
    }
    return '<!-- ERR 2 -->';
    }

    /* private */ function closeList( $char ) {
    if ( '*' === $char ) { $text = '</li></ul>'; }
    elseif ( '#' === $char ) { $text = '</li></ol>'; }
    elseif ( ':' === $char ) {
        if ( $this->mDTopen ) {
            $this->mDTopen = false;
            $text = '</dt></dl>';
        } else {
            $text = '</dd></dl>';
        }
    }
    else {	return '<!-- ERR 3 -->'; }
    return $text."\n";
    }
    /**#@-*/
}
?>