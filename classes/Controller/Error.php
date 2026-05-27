<?class Controller_Error extends Controller_Template {

    
	public $template = 'error_page';

    public function before()
    {
        parent::before();
		// Получаем статус ошибки
        $status = (int) $this->request->action();

        // Получаем сообщение об ошибке
        if (Request::$initial !== Request::$current)
        {
            $message = rawurldecode($this->request->param('message'));

            if ($message)
            {
                $this->template->message = $message;
            }
        }
        else
        {
            $this->request->action(404);
        }
        $this->response->status($status);
    }

    public function action_404()
    {
        $this->template->title = 'Запрашиваемая страница не найдена.';
    }

    public function action_503()
    {
        $this->template->title = 'Сервис временно недоступен.';
    }

    public function action_500()
    {
        $this->template->title = 'Внутренняя ошибка сервера.';
    }
}
?>