<?php

namespace BEM;

use yii\helpers\ArrayHelper;

class BH
{

    static public $toHtmlCallId = 0;

    /**
     * Используется для идентификации шаблонов.
     * Каждому шаблону дается уникальный id для того, чтобы избежать повторного применения
     * шаблона к одному и тому же узлу BEMJSON-дерева.
     * @var integer
     * @protected
     */
    protected $lastMatchId = 0;

    /**
     * Плоский массив для хранения матчеров.
     * Каждый элемент — массив с двумя элементами: [{String} выражение, {Function} шаблон}]
     * @var Matcher[]
     * @protected
     */
    protected $matchers = [];

    protected $declByBlock = [];

    /**
     * Неймспейс для библиотек. Сюда можно писать различный функционал для дальнейшего использования в шаблонах.
     *
     * ```javascript
     * $bh->lib->i18n = BEM.I18N;
     * $bh->lib->objects = bh.lib.objects || [];
     * $bh->lib->objects.inverse = bh.lib.objects.inverse || function(obj) { ... };
     * ```
     * @var \ArrayObject
     */
    public $lib;

    /**
     * Опции BH. Задаются через setOptions.
     * @var array
     */
    protected $options = [];

    protected $optJsAttrName = 'onclick';
    protected $optJsAttrIsJs = true;
    protected $optJsCls = 'i-bem';
    protected $optJsElem = true;
    protected $optEscapeContent = false;
    protected $optNobaseMods = false;

    /**
     * Разделитель модификатора от блока/элемента
     * Для БЭМ-стиля Гарри Робертса http://csswizardry.com/work/ (блок__элемент--модификатор) использовать значение '--'
     * @var string
     */
    protected $optModsDelimiter = '_';

    /**
     * @var bool Форматировать HTML отступами
     */
    protected $optIndent = false;

    /**
     * Флаг, включающий автоматическую систему поиска зацикливаний. Следует использовать в development-режиме,
     * чтобы определять причины зацикливания.
     * @var boolean
     * @protected
     */
    protected $infiniteLoopDetection = false;

    protected $ctx = null;

    static protected $selfCloseHtmlTags = [
        'area' => 1,
        'base' => 1,
        'br' => 1,
        'col' => 1,
        'command' => 1,
        'embed' => 1,
        'hr' => 1,
        'img' => 1,
        'input' => 1,
        'keygen' => 1,
        'link' => 1,
        'menuitem' => 1,
        'meta' => 1,
        'param' => 1,
        'source' => 1,
        'track' => 1,
        'wbr' => 1
    ];

    protected $matcherCalls = 0;

    protected $matcherBuilt = false;

    /**
     * Buffer for toHtml
     * @var String
     */
    protected $buf = null;

    /**
     * BH: BEMJSON -> HTML процессор.
     * @constructor
     */
    public function __construct()
    {
        require_once __DIR__ . '/helpers.php';
        $this->lib = new \ArrayObject([], \ArrayObject::ARRAY_AS_PROPS);
    }


    /**
     * Задает опции шаблонизации.
     *
     * @chainable
     * @param array $options
     *        string $options[jsAttrName] Атрибут, в который записывается значение поля `js`. По умолчанию, `onclick`.
     *        string $options[jsAttrScheme] Схема данных для `js`-значения.
     *               Форматы:
     *                     `js` — значение по умолчанию. Получаем `return { ... }`.
     *                     `json` — JSON-формат. Получаем `{ ... }`.
     * @return BH $this
     */
    public function setOptions($options)
    {
        $this->options = [];
        foreach ($options as $k => $option) {
            $this->options[$k] = $options[$k];
        }

        if (isset($options['jsAttrName'])) {
            $this->optJsAttrName = $options['jsAttrName'];
        }

        if (isset($options['jsAttrScheme'])) {
            $this->optJsAttrIsJs = $options['jsAttrScheme'] === 'js';
        }

        if (isset($options['jsCls'])) {
            $this->optJsCls = $options['jsCls'];
        }

        if (isset($options['jsElem'])) {
            $this->optJsElem = $options['jsElem'];
        }

        if (isset($options['clsNobaseMods'])) {
            $this->optNobaseMods = $options['clsNobaseMods'];
        }

        if (isset($options['escapeContent'])) {
            $this->optEscapeContent = $options['escapeContent'];
        }

        if (isset($options['modsDelimiter'])) {
            $this->optModsDelimiter = $options['modsDelimiter'];
        }
        if (isset($options['indent'])) {
            $this->optIndent = $options['indent'];
        }

        return $this;
    }

    /**
     * Возвращает опции шаблонизации.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Включает/выключает механизм определения зацикливаний.
     *
     * @chainable
     * @param boolean $enable
     * @return BH
     */
    public function enableInfiniteLoopDetection($enable)
    {
        $this->infiniteLoopDetection = $enable;
        return $this;
    }

    /**
     * Преобразует BEMJSON в HTML-код
     *
     * @param array|string $bemJson
     * @return string
     */
    public function apply($bemJson)
    {
        return $this->toHtml($this->processBemJson($bemJson));
    }

    /**
     * Объявляет шаблон.
     * ```javascript
     * bh.match('page', function(ctx) {
     *     ctx.mix([{ block: 'ua' }]);
     *     ctx.cls('ua_js_no ua_css_standard');
     * });
     * bh.match('block_mod_modVal', function(ctx) {
     *     ctx.tag('span');
     * });
     * bh.match('block__elem', function(ctx) {
     *     ctx.attr('disabled', 'disabled');
     * });
     * bh.match('block__elem_elemMod', function(ctx) {
     *     ctx.mix([{ block: 'link' }]);
     * });
     * bh.match('block__elem_elemMod_elemModVal', function(ctx) {
     *     ctx.mod('active', 'yes');
     * });
     * bh.match('block_blockMod__elem', function(ctx) {
     *     ctx.param('checked', true);
     * });
     * bh.match('block_blockMod_blockModVal__elem', function(ctx) {
     *     ctx.content({
     *         elem: 'wrapper',
     *         content: ctx
     *     };
     * });
     * ```
     *
     * @chainable
     * @param string|array $expr bemSelector or map with matchers
     * @param Matcher|\Closure $matcher
     * @return BH
     */
    public function match($expr, $matcher = null)
    {
        if (!$expr) {
            return $this;
        }

        if (isList($expr)) {
            foreach ($expr as $match) {
                $this->match($match, $matcher);
            }
            return $this;
        }

        if (is_array($expr)) {
            foreach ($expr as $k => $matcherFromExpr) {
                $this->match($k, $matcherFromExpr);
            }
            return $this;
        }
        if ($matcher instanceof Matcher === false) {
            $matcher = new Matcher($expr, $matcher);
        }

        $this->matchers[] = $matcher;
        if ($matcher->id === null) {
            $matcher->id = $this->lastMatchId;
        }

        $this->lastMatchId++;

        // cleanup cached matcher to rebuild it on next render

        $this->resetMatcherBuild();


        return $this;
    }

    /**
     * Adds matcher returning it's ID
     * @param $expr
     * @param $matcher
     *
     * @return int
     */
    public function addMatcher($expr, $matcher)
    {
        $id = $this->lastMatchId;
        $this->match($expr, $matcher);
        return $id;
    }

    public function addMatcherList($list)
    {
        $ids = [];
        foreach ($list as &$matcher) {
            $ids[] = $this->matcher($matcher);
        }
        return $ids;
    }

    /**
     * Add matcher by reference to Mathcer
     * @param Matcher $matcher
     */
    public function matcher(&$matcher)
    {
        $this->match($matcher->expression, $matcher);
        return $matcher->id;
    }

    /**
     * Removes matcher by it's id
     * @param $ids
     *
     * @return bool
     */
    public function removeMatcherById($ids)
    {
        $ids = (array) $ids;
        $removed = 0;
        foreach ($ids as $id) {
            foreach ($this->matchers as $i => $matcher) {
                if ($matcher->id === $id) {
                    unset($this->matchers[$i]);
                    $this->resetMatcherBuild();

                    $removed++;
                }
            }
        }
        return count($ids)===$removed;
    }

    /**
     * Resets matcherBuilt state
     */
    public function resetMatcherBuild()
    {
        $this->matcherBuilt = false;
        $this->declByBlock = [];
    }

    /**
     * Объявляет глобальный шаблон, применяемый перед остальными.
     *
     * ```php
     * $bh->beforeEach(function ($ctx, $json) {
     *     $ctx->attr('onclick', $json->counter);
     * });
     * ```
     *
     * @param \Closure|Matcher $matcher
     * @return BH
     */
    public function beforeEach($matcher)
    {
        return $this->match('$before', $matcher);
    }

    /**
     * Объявляет глобальный шаблон, применяемый после остальных.
     *
     * ```php
     * $bh->afterEach(function ($ctx) {
     *     $ctx->tag('xdiv');
     * });
     * ```
     *
     * @param \Closure|Matcher $matcher
     * @return BH
     */
    public function afterEach($matcher)
    {
        return $this->match('$after', $matcher);
    }

    public function buildMatcher()
    {
        if ($this->matcherBuilt === false) {

            $this->matcherCalls = 0;
            $declarations = [];
            // Matchers->iterate
            $reversedMatchers = array_reverse($this->matchers);
            foreach ($reversedMatchers as $i => &$matcher) {
                $decl = static::parseBemCssClasses($matcher->expression, $this->optModsDelimiter);

                $decl['matcher'] = &$matcher;
                $decl['index'] = $i;
                $declarations[] = $decl;
            }
            $this->declByBlock = static::groupBy($declarations, 'block');
            foreach ($this->declByBlock as $blockName => &$blockData) {
                if ($blockName !== '$before' && $blockName !== '$after') {
                    $blockData = static::groupBy($blockData, 'elem');
                }
            }

            $this->matcherBuilt = true;
        }
    }

    public function matcherImpl($ctx, $json)
    {
        $declByBlock = $this->declByBlock;
        // run before
        if (isset($declByBlock['$before'])) {
            foreach ($declByBlock['$before'] as $decl) {
                $fnId = $decl['matcher']->id;

                $json->_m[$fnId] = true;
                $subRes = $decl['matcher']($ctx, $json);
                if ($subRes !== null) {
                    return ($subRes ? : "");
                }
                if ($json->_stop) {
                    return null;
                }

            }
        }

        // loop through all matchers
        $afterEach = isset($declByBlock['$after']) ? $declByBlock['$after'] : null;
        unset($declByBlock['$before'], $declByBlock['$after']);

        $thisBlock = $json->block ?: __undefined;
        $thisElem = $json->elem ?: __undefined;

        foreach ($declByBlock as $blockName => $blockData) {
            if ($blockName === $thisBlock) {
                foreach ($blockData as $elemName => $decls) {
                    if ($thisElem === $elemName) {
                        foreach ($decls as $decl) {
                            $__id = $decl['matcher']->id;
                            $valid = !isset($json->_m[$__id]);
                            if ($valid && isset($decl['elemMod'])) {
                                $modKey = $decl['elemMod'];

                                $valid = $valid && isset($json->elemMods)
                                    && $json->elemMods->{$modKey} === $decl['elemModVal'];
                            }
                            if ($valid && isset($decl['blockMod'])) {
                                $modKey = $decl['blockMod'];
                                $valid = $valid && isset($json->mods)
                                    && $json->mods->{$modKey} === $decl['blockModVal'];
                            }
                            if ($valid === true) {
                                $json->_m[$__id] = true;
                                $subRes = $decl['matcher']($ctx, $json);
                                if ($subRes !== null) {
                                    return ($subRes ? : "");
                                }
                                if ($json->_stop) {
                                    return null;
                                }
                            }
                        }
                        break;
                    }
                }
                break;
            }
        }


        // run after
        if ($afterEach !== null) {
            foreach ($afterEach as $decl) {
                $fnId = $decl['matcher']->id;

                $json->_m[$fnId] = true;
                $subRes = $decl['matcher']($ctx, $json);
                if ($subRes !== null) {
                    return ($subRes ? : "");
                }
                if ($json->_stop) {
                    return null;
                }

            }
        }
        return null;
    }

    /**
     * Раскрывает BEMJSON, превращая его из краткого в полный.
     * @param Json|array $bemJson
     * @param string [$blockName]
     * @param boolean [$ignoreContent]
     * @throws \Exception
     * @return Json
     */
    public function processBemJson($bemJson, $blockName = null, $ignoreContent = null)
    {
        if (empty($bemJson)) {
            return is_array($bemJson)
                ? '<div></div>'
                : '';
        }

        // trying to parse
        if (is_scalar($bemJson)) {
            // string? like '{...}' || '[{...}]' || '([...])'
            if (!is_string($bemJson)) {
                // return as is
                return (string)$bemJson;
            }

            // deprecated feature:
            $bemJson = trim($bemJson, "\n\t\r ()\x0B\0");
            $c = $bemJson[0];
            $l = $bemJson[strlen($bemJson) - 1];
            if (($c === '{' && $l === '}') || ($c === '[' && $l === ']')) {
                // if it looks like json object - parse and process
                return $this->processBemJson(weakjson_decode($bemJson));
            } else {
                // return as is
                return $bemJson;
            }
        }

        $resultArr = JsonCollection::normalize($bemJson);
        if (count($resultArr) > 1) {
            $resultArr = new \ArrayObject([$resultArr]);
        }

        $bemJson = $resultArr[0];

        $steps = [];
        $steps[] = new Step(
            $bemJson,
            $resultArr,
            0,
            $blockName,
            null
        );

        if (!$this->matcherBuilt) {
            $this->buildMatcher();
        }


        $processContent = !$ignoreContent;
        $infiniteLoopDetection = $this->infiniteLoopDetection;

        $ctx = new Context($this);

        // js: while (node = nodes.shift()) {
        while ($step = array_shift($steps)) {
            $json = $step->json;
            $blockName = $step->block;
            $blockMods = $step->mods;

            if ($json instanceof JsonCollection) {
                $j = 0;
                $arr = $json;
                foreach ($arr as $i => $child) {
                    if (!is_object($child)) {
                        continue;
                    }
                    // walk each bem node inside collection
                    $steps[] = new Step(
                        $child,
                        $arr,
                        $i,
                        $blockName,
                        $blockMods,
                        ++$j,
                        $step
                    );
                }
                $arr->_listLength = $j;

                $step->arr[$step->index] = $json;
                continue;
            }

            $stopProcess = false;

            if (is_scalar($json) || empty($json)) {
                // skip
                continue;

            } elseif ($json->elem) {
                $blockName = $json->block = $json->block ?: $blockName;
                if (!isset($json->elemMods)) {
                    $json->elemMods = $json->mods;
                    $json->mods = null;
                }
                $blockMods = $json->mods = isset($json->mods) ? $json->mods : $blockMods;

            } elseif ($json->block) {
                $blockName = $json->block;
                $blockMods = $json->mods;
            }

            if ($json instanceof Json) {
                if ($infiniteLoopDetection) {
                    $json->_matcherCalls++;
                    $this->matcherCalls++;
                    if ($json->_matcherCalls > 100) {
                        throw new \Exception(
                            'Infinite json loop detected at "' .
                            $json->block .
                            ($json->elem ? '__' . $json->elem : '') .
                            '".'
                        );
                    }
                    if ($this->matcherCalls > 1000) {
                        throw new \Exception(
                            'Infinite matcher loop detected at "' .
                            $json->block .
                            ($json->elem ? '__' . $json->elem : '') .
                            '".'
                        );
                    }
                }

                if (!$json->_stop) {
                    $ctx->node = $step;
                    $ctx->ctx = $json;
                    $subRes = $this->matcherImpl($ctx, $json);
                    if ($subRes !== null) {
                        $json = JsonCollection::normalize($subRes);
                        $step->json = $json;
                        $step->block = $blockName;
                        $step->mods = $blockMods;
                        $steps[] = $step;
                        $stopProcess = true;
                    }
                }
            }

            if (!$stopProcess && $processContent && isset($json->content) && !is_scalar($json->content)) {
                $content = $json->content;

                $j = 0;
                foreach ($content as $i => $child) {
                    if (is_scalar($child) || empty($child)) {
                        continue;
                    }

                    $steps[] = new Step(
                        $child,
                        $content,
                        $i,
                        $blockName,
                        $blockMods,
                        ++$j,
                        $step
                    );
                }
                $content->_listLength = $j;
            }
        }

        return $resultArr[0];
    }

    /**
     * Превращает раскрытый BEMJSON в HTML.
     * @param Json|array $json
     * @return string
     */
    public function toHtml($json)
    {
        $this->buf = '';
        $this->html($json);
        $buf = $this->buf;
        unset($this->buf);
        return $buf;
    }

    public function html($json, $nestingLevel = 0)
    {
        if (!$json) {
            $this->buf .= (string)$json;
            return;
        }

        if (is_scalar($json)) {
            $this->buf .= $this->optEscapeContent ? self::xmlEscape($json) : $json;
            return;
        }

        if (isList($json)) {
            foreach ($json as $item) {
                if ($item !== false && $item !== null) {
                    $this->html($item, $nestingLevel+1);
                }
            }
            return;
        }

        if ($json->tag === false || $json->tag === '') {
            if ($json->html) {
                $this->buf .= $json->html;
            } else {
                $this->html($json->content, $nestingLevel+1);
            }
            return;
        }

        $cls = '';
        $attrs = '';
        $hasMixJsParams = false;

        if (!empty($json->attrs)) {
            foreach ($json->attrs as $jkey => $jval) {
                if ($jval === true) {
                    $attrs .= ' ' . $jkey;
                } elseif ($jval !== null && $jval !== false) {
                    $attrs .= ' ' . $jkey /*escape?*/ . '="' . static::attrEscape($jval) . '"';
                }
            }
        }

        if ($json->toHtml) {
            $toHtml = $json->toHtml->bindTo($this);
            $this->buf .= $toHtml($json) ?: '';
            return;
        }

        if ($json->bem !== false) {
            // hardcoded naming
            $base = ($json->block ? $json->block : '') . ($json->elem ? '__' . $json->elem : '');

            $jsParams = false;
            if ($json->block) {
                $cls = static::toBemCssClasses($json, $base, null, $this->optNobaseMods, $this->optModsDelimiter);
                if ($json->js !== null && $json->js !== false) {
                    /** @var array $jsParams */
                    $jsParams = [];
                    $jsParams[$base] = $json->js === true ? [] : static::filterNulls($json->js);
                }
            }

            $addJSInitClass = $this->optJsCls && ($this->optJsElem || !$json->elem);

            if ($json->mix) {
                foreach ($json->mix as $mix) {
                    if (!$mix || $mix->bem === false) {
                        continue;
                    }

                    // var mixBlock = mix.block || json.block || ''
                    $mixBlock = $mix->block ?: $json->block;
                    if (!$mixBlock) {
                        continue;
                    }

                    // mixElem = mix.elem || (mix.block ? null : json.block && json.elem)
                    $mixElem = $mix->elem ?: ($mix->block ? null : ($json->block ? $json->elem : null));
                    $mixBase = $mixBlock . ($mixElem ? '__' . $mixElem : '');

                    $cls .= static::toBemCssClasses(
                        $mix,
                        $mixBase,
                        $base,
                        $this->optNobaseMods,
                        $this->optModsDelimiter
                    );
                    if ($mix->js !== null && $mix->js !== false) {
                        $jsParams = $jsParams ?: [];
                        $jsParams[$mixBase] = $mix->js === true ? [] : static::filterNulls($mix->js);
                        $hasMixJsParams = true;
                        if (!$addJSInitClass) {
                            $addJSInitClass = $mixBlock && ($this->optJsCls && ($this->optJsElem || !$mixElem));
                        }
                    }
                }
            }

            if ($jsParams) {
                if ($addJSInitClass) {
                    $cls .= ' ' . $this->optJsCls;
                }
                $jsData = !$hasMixJsParams && $json->js === true ?
                    '{&quot;' . $base . '&quot;:{}}' :
                    self::attrEscape(
                        str_replace(
                            '[]',
                            '{}',
                            json_encode($jsParams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                        )
                    );
                $attrs .= ' ' . ($json->jsAttr ?: $this->optJsAttrName) . '="' .
                    ($this->optJsAttrIsJs ? 'return ' . $jsData : $jsData) . '"';
            }
        }

        $cls = (string)$cls;
        if ($json->cls !== null) {
            $cls .= ($cls ? ' ' : '') . trim($json->cls);
        }

        $identation = '';
        if ($this->optIndent !== false) {
            $identation = "\n" . str_repeat($this->optIndent, $nestingLevel);
        }

        $tag = $json->tag !== null ? $json->tag : 'div';
        $this->buf .= $identation . '<' . $tag . ($cls ? ' class="' . $cls . '"' : '') . ($attrs ? $attrs : '');

        if (isset(static::$selfCloseHtmlTags[$tag])) {
            $this->buf .= '/>';
            return;
        }

        $this->buf .= '>';
        if ($this->optIndent !== false) {
            $this->buf .= "\n" . str_repeat($this->optIndent, $nestingLevel+1);
        }
        if (!empty($json->html)) {
            $this->buf .= $json->html;

        } elseif ($json->content !== null) {
            $this->html($json->content, $nestingLevel);
        }
        $this->buf .= $identation . '</' . $tag . '>';
        if ($this->optIndent !== false) {
            $this->buf .= "\n";
        }
    }

    // todo: add encoding here
    public static function xmlEscape($s)
    {
        return htmlspecialchars($s, ENT_NOQUOTES);
    }

    /**
     * Escapes attribute or cls.
     * New BC-breaking feature added: ability to store unsafe(unescaped) values.
     * Example of unsafe:
     * ```
     * [
     *      'block' => 'myblock',
     *      'cls' => [
     *          'unsafe' => '<?= isset($item["red"])?"red":"" ?>',
     *      ],
     *      'attrs' => [
     *          'data-key' => [
     *              'unsafe' => '<?= $key ?>',
     *          ],
     *      ],
     *  ],
     * ```
     * Will produce:
     * ```
     * <div class="myblock <?= isset($item["red"])?"red":"" ?>" data-key="<?= $key ?>"></div>
     * ```
     * Which is good for PHP-templates.
     *
     * @param $s
     *
     * @return string
     */
    public static function attrEscape($s)
    {
        if (is_bool($s)) {
            return $s ? 'true' : 'false';
        } elseif (isset($s['unsafe'])) {
            return $s['unsafe'];
        }
        return htmlspecialchars($s, ENT_QUOTES);
    }

    public static function toBemCssClasses($json, $base, $parentBase = null, $nobase = false, $modsDelimiter = '_')
    {
        $res = '';

        if ($parentBase !== $base) {
            if ($parentBase) {
                $res .= ' ';
            }
            $res .= $base;
        }

        // if (mods = json.elem && json.elemMods || json.mods)
        $mods = $json->elem && isset($json->elemMods) ? $json->elemMods : $json->mods;
        foreach ($mods as $k => $mod) {
            if ($mod || $mod === 0) {
                if (is_numeric($k)) {
                    // we have numeric-based key
                    $res .= ' ' . ($nobase ? '' : $base) . $modsDelimiter . $mod;
                } else {
                    $res .= ' ' . ($nobase ? '' : $base) . $modsDelimiter . $k . ($mod === true ? '' : '_' . $mod);
                }
            }
        }

        return $res;
    }

    // @todo fixup hardcoded leveling
    public static function parseBemCssClasses ($expr, $modsDelimiter = '_')
    {
        list ($blockBits, $elemBits) = explode('__', $expr . "__\1");
        $delim = preg_quote($modsDelimiter);

        preg_match("#^(.*)($delim(.*))?$#Us", $blockBits, $matches);
        if (isset($matches[1])) {
            $block = $matches[1];
        } else {
            $block = null;
        }
        if (isset($matches[3])) {
            $blockModFull = $matches[3];
        } else {
            $blockModFull = null;
        }


        list ($blockMod, $blockModVal) = explode('_', $blockModFull . "_\1", 3);

        $blockMod = $blockMod === "\1" ? null : $blockMod;
        if (empty($blockMod)) {
            $blockMod = null;
        }
        $blockModVal = $blockMod ? ($blockModVal !== "\1" ? $blockModVal : true) : null;
        if (empty($blockModVal)) {
            $blockModVal = null;
        }

        if ($elemBits !== "\1") {

            preg_match("#^(.*)($delim(.*))?$#Us", $elemBits, $matches);

            if (isset($matches[1])) {
                $elem = $matches[1];
            } else {
                $elem = null;
            }
            if (isset($matches[3])) {
                $elemModFull = $matches[3];
            } else {
                $elemModFull = null;
            }
            list ($elemMod, $elemModVal) = explode('_', $elemModFull . "_\1_\1");
            $elemMod = $elemMod === "\1" ? null : $elemMod;
            if (empty($elemMod)) {
                $elemMod = null;
            }
            $elemModVal = $elemMod ? ($elemModVal !== "\1" ? $elemModVal : true) : null;
        }

        return compact('block', 'blockMod', 'blockModVal', 'elem', 'elemMod', 'elemModVal');
    }

    /**
     * Group up selectors by some key
     * @param array $data
     * @param string $key
     * @return array
     */
    public static function groupBy($data, $key)
    {
        $res = [];
        foreach ($data as $i=>$item) {
            $value = empty($item[$key]) ? __undefined : $item[$key];
            if (empty($res[$value])) {
                $res[$value] = [];
            }
            $res[$value][] = $item;
        }
        return $res;
    }

    protected static function filterNulls($arr)
    {
        return array_filter($arr, function ($e) {
            return $e !== null;
        });
    }
}
