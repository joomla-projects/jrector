<?php

class ExampleView extends JViewLegacy
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $this->assign('items', $items);
        $this->assignRef('state', $this->get('State'));

        parent::display($tpl);
    }
}
-----
<?php

class ExampleView extends JViewLegacy
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $this->items = $items;
        $this->state = $this->get('State');

        parent::display($tpl);
    }
}