<?php

namespace Acme\Component\Example\Site\View\Unknown;

// The derived model class does not exist, so the getters cannot be verified.
class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
    }
}
