<?php

// Without a View\<Name> namespace no model class can be derived.
class HtmlView extends BaseHtmlView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
    }
}
