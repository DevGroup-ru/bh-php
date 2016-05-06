<?php

namespace BEM;

class Matcher
{
    /**
     * @var string
     */
    public $expression = '';

    /** @var callable */
    public $matcher;

    /**
     * @var null
     */
    public $id = null;

    public function __construct($expression, $matcher, $id = null)
    {
        $this->expression = $expression;
        $this->matcher = $matcher;
        $this->id = $id;
    }

    public function __invoke(Context $ctx, Json $json)
    {
        $matcher = &$this->matcher;
    
        return $matcher($ctx, $json);
    }
}
