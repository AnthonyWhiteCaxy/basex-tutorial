xquery version "3.1";

declare default element namespace 'http://www.w3.org/1999/xhtml';
declare namespace epub = 'http://www.idpf.org/2007/ops';
declare namespace m = 'http://www.w3.org/1998/Math/MathML';
declare namespace uuid = 'java:java.util.UUID';

declare option output:method 'xhtml';
declare option output:encoding 'UTF-8';

declare variable $dbname as xs:string external;
declare variable $bookId as xs:string external;
declare variable $sectionId as xs:string external;
declare variable $includeChildren as xs:boolean external := false();

declare function local:remove-elements($input as element(), $remove-names as xs:string*) as element() {
    element {node-name($input) }
    {$input/@*,
    for $child in $input/node()[not(name(.)=$remove-names) and not(contains(concat(' ', normalize-space(@class), ' '), ' definitions ') and not($includeChildren))]
    return
        if ($child instance of element())
        then local:remove-elements($child, $remove-names)
        else $child
    }
};

for $section in collection($dbname)//section[@id=concat($bookId, '')]//*[@id=concat($sectionId, '')]
    return (
        if ($includeChildren)
        then local:remove-elements($section, ('m:math'))
        else local:remove-elements($section, ('section', 'figure', 'm:math'))
    )
