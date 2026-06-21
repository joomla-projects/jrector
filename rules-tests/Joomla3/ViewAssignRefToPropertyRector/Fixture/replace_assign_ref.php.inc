<?php

class ExampleView extends JView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $this->assign('items', $items);
        $this->assign('user', JFactory::getUser());
        $this->assign('state', $this->get('State'));
        $this->assignRef('items', $items);
        $this->assignRef('user', JFactory::getUser());
        $this->assignRef('state', $this->get('State'));

        parent::display($tpl);
    }
}
-----
<?php

class ExampleView extends JView
{
    public function display($tpl = null)
    {
        $items = $this->get('Items');
        $this->items = $items;
        $this->user = JFactory::getUser();
        $this->state = $this->get('State');
        $this->items = $items;
        $this->user = JFactory::getUser();
        $this->state = $this->get('State');

        parent::display($tpl);
    }
}
