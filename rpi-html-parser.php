<?php

class rpiHTMLParser
{
    private string $updated_post_content = '';

    public function parse_html($content): string
    {
        $doc = new DOMDocument();
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
        $this->showDOMNode($doc);

        // return the content.
        return $this->updated_post_content;

    }

    private function showDOMNode(DOMNode $domNode)
    {
        foreach ($domNode->childNodes as $node) {
            if (!empty($node->nodeName) && !in_array($node->nodeName,array('html', 'xml', 'body')) ) {
                $new_content = $domNode->ownerDocument->saveHTML($node);
                switch ($node->nodeName) {
                    case 'h1':
                    case 'h2':
                    case 'h3':
                    case 'h4':
                    case 'h5':
                    case 'h6':
                        $this->createNodeTemplate($new_content, 'core/heading', $node->nodeName);
                        break;
                    case 'p':
                        $this->createNodeTemplate($new_content, 'core/paragraph');
                        break;
                    case 'ul':
                    case 'ol':
                        $this->createNodeTemplate($new_content, 'core/List');
                        break;
                    case 'figure':
                        $this->createNodeTemplate($new_content, 'core/html');
                        break;
                    default:
                        $this->createNodeTemplate($new_content, 'core/freeform');
                        break;
                }
            } elseif ($node->hasChildNodes()) {
                $this->showDOMNode($node);
            }
        }
    }

    private function createNodeTemplate($new_content, $blockName, $nodeName = '')
    {
        if (!empty($blockName)) {
            $new_block = array(
                // We keep this the same.
                'blockName' => $blockName,
                // also add the class as block attributes.
                'attrs' => array('className' => 'import'),
                // I'm guessing this will come into play with group/columns, not sure.
                'innerBlocks' => array(),
                // The actual content.
                'innerHTML' => $new_content,
                // Like innerBlocks, I guess this will be used for groups/columns.
                'innerContent' => array($new_content),
            );
            if(!empty($nodeName))
            {;
                $new_block['attrs']['level'] = (int)trim($nodeName,'h');
            }
            $this->updated_post_content .= serialize_block($new_block);
        }
    }
}