<?php

use Stevebauman\Purify\Cache\FilesystemDefinitionCache;
use Stevebauman\Purify\Definitions\Html5Definition;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Config
    |--------------------------------------------------------------------------
    |
    | This option defines the default config that is provided to HTMLPurifier.
    |
    */

    'default' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Config sets
    |--------------------------------------------------------------------------
    |
    | Here you may configure various sets of configuration for differentiated use of HTMLPurifier.
    | A specific set of configuration can be applied by calling the "config($name)" method on
    | a Purify instance. Feel free to add/remove/customize these attributes as you wish.
    |
    | Documentation: http://htmlpurifier.org/live/configdoc/plain.html
    |
    |   Core.Encoding               The encoding to convert input to.
    |   HTML.Doctype                Doctype to use during filtering.
    |   HTML.Allowed                The allowed HTML Elements with their allowed attributes.
    |   HTML.ForbiddenElements      The forbidden HTML elements. Elements that are listed in this
    |                               string will be removed, however their content will remain.
    |   CSS.AllowedProperties       The Allowed CSS properties.
    |   AutoFormat.AutoParagraph    Newlines are converted in to paragraphs whenever possible.
    |   AutoFormat.RemoveEmpty      Remove empty elements that contribute no semantic information to the document.
    |
    */

    'configs' => [

        'default' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'h1,h2,h3,h4,h5,h6,b,u,strong,i,em,s,del,a[href|title],ul,ol,li,p[style],br,span,img[width|height|alt|src],blockquote',
            'HTML.ForbiddenElements' => '',
            'CSS.AllowedProperties' => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],

        /*
         * Rich text from TinyMCE (Mary editor): lists, alignment, tables, images, links, colors.
         * Used for sanitizing on save and again on display (defense in depth).
         */
        'tinymce' => [
            'Core.Encoding' => 'utf-8',
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => implode(',', [
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'p[style|class]', 'br', 'hr',
                'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del', 'sub', 'sup',
                'blockquote[class|style]',
                'code', 'pre',
                'span[style|class]',
                'a[href|title|target|rel]',
                'ul[style|class]', 'ol[style|class]', 'li[style|class]',
                'img[src|alt|title|width|height|style|class]',
                'table[style|class]', 'thead', 'tbody', 'tfoot', 'caption',
                'tr[style|class]', 'th[colspan|rowspan|style|class]', 'td[colspan|rowspan|style|class]',
                'figure', 'figcaption',
            ]),
            'HTML.ForbiddenElements' => '',
            'CSS.AllowedProperties' => implode(',', [
                'font', 'font-size', 'font-weight', 'font-style', 'font-family',
                'text-decoration', 'padding', 'padding-left', 'padding-right', 'margin', 'margin-left',
                'color', 'background-color', 'text-align', 'vertical-align', 'border', 'border-collapse',
                'width', 'height', 'max-width',
            ]),
            'Attr.AllowedFrameTargets' => ['_blank', '_self'],
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | HTMLPurifier definitions
    |--------------------------------------------------------------------------
    |
    | Here you may specify a class that augments the HTML definitions used by
    | HTMLPurifier. Additional HTML5 definitions are provided out of the box.
    | When specifying a custom class, make sure it implements the interface:
    |
    |   \Stevebauman\Purify\Definitions\Definition
    |
    | Note that these definitions are applied to every Purifier instance.
    |
    | Documentation: http://htmlpurifier.org/docs/enduser-customize.html
    |
    */

    'definitions' => Html5Definition::class,

    /*
    |--------------------------------------------------------------------------
    | HTMLPurifier CSS definitions
    |--------------------------------------------------------------------------
    |
    | Here you may specify a class that augments the CSS definitions used by
    | HTMLPurifier. When specifying a custom class, make sure it implements
    | the interface:
    |
    |   \Stevebauman\Purify\Definitions\CssDefinition
    |
    | Note that these definitions are applied to every Purifier instance.
    |
    | CSS should be extending $definition->info['css-attribute'] = values
    | See HTMLPurifier_CSSDefinition for further explanation
    |
    */

    'css-definitions' => null,

    /*
    |--------------------------------------------------------------------------
    | Serializer
    |--------------------------------------------------------------------------
    |
    | The storage implementation where HTMLPurifier can store its serializer files.
    | If the filesystem cache is in use, the path must be writable through the
    | storage disk by the web server, otherwise an exception will be thrown.
    |
    */

    /*
     * Filesystem serializer avoids depending on the app cache store (e.g. database) for
     * HTMLPurifier definition cache — important for tests and environments without DB/cache.
     */
    'serializer' => [
        'disk' => env('FILESYSTEM_DISK', 'local'),
        'path' => 'purify',
        'cache' => FilesystemDefinitionCache::class,
    ],

];
