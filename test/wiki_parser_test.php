<?php
include_once '../wiki_parser.php';
$text = <<<EOF
=Title1=
==Title2==
===Title3===
====Title4====
=====Title5=====
Normal
''Italic''
'''Bold'''
'''''Bold Italic'''''
* List
** List2
*** List3
# Order list
## Order list2
### Order list3
EOF;
$parser = new zyfra_wiki_parser;
print $parser->parse($text);
?>