<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Este é o controller de categorias, usado principalmente
| no painel de controle do site.
|
| @author Eliel de Paula <elieldepaula@gmail.com>
| @since 21/10/2014
|--------------------------------------------------------------------------
*/

class Agendas extends MX_Controller {

	function __construct()
	{
		$this->auth->protect('agendas');
		$this->form_validation->set_error_delimiters('<p><span class="label label-danger">', '</span></p>');
	}

	public function index()
	{
		$this->load->model('post');
		$this->load->library('table');

		$layout_vars = array();
		$content_vars = array();

		// Template da tabela
		$this->table->set_template(array('table_open'  => '<table id="grid" class="table table-striped">')); 
		$this->table->set_heading('#', 'Título', 'Data', 'Status', 'Ações');
		$query = $this->post->get_by_field('page','2', array('field'=>'created','order'=>'desc'));//, array('offset'=>'0','limit'=>'2'));

		foreach($query->result() as $row)
		{
			$this->table->add_row(
				$row->id, 
				$row->title, 
				datetime_for_user($row->created, false), 
				status_post($row->status),
				// Ícones de ações
				div(array('class'=>'btn-group btn-group-sm')).
				anchor('admin/agendas/edit/'.$row->id, glyphicon('edit'), array('class' => 'btn btn-default')).
				'<button class="btn btn-default" onClick="return confirmar(\''.site_url('admin/agendas/delete/'.$row->id).'\');">'.glyphicon('trash').'</button>' .
				div(null,true)

				);
		}

		$content_vars['listagem'] = $this->table->generate();
		
		$this->wpanel->load_view('agendas/index', $content_vars);
	}

	public function add()
	{
		
		$layout_vars = array();
		$content_vars = array();

		$this->form_validation->set_rules('title', 'Título', 'required');
		$this->form_validation->set_rules('created', 'Data', 'required');
		
		if ($this->form_validation->run() == FALSE)
		{
			$this->load->model('categoria');
			// Prepara a lista de categorias.
			$query = $this->categoria->get_list();
			$categorias = array();
			foreach($query->result() as $row){
				$categorias[$row->id] = $row->title;
			}

			$content_vars['categorias'] = $categorias;
			
			$this->wpanel->load_view('agendas/add', $content_vars);

		} else {

			$this->load->model('post');

			$dados_save = array();
			$dados_save['user_id'] = $this->auth->get_userid();
			$dados_save['title'] = $this->input->post('title');
			$dados_save['description'] = $this->input->post('description');
			$dados_save['link'] = strtolower(url_title(convert_accented_characters($this->input->post('title'))));
			$dados_save['content'] = $this->input->post('content');
			$dados_save['tags'] = $this->input->post('tags');
			$dados_save['status'] = $this->input->post('status');
			$dados_save['created'] = datetime_for_mysql($this->input->post('created') . ' 12:00:00'); //date('Y-m-d H:i:s', strtotime($this->input->post('created')));
			$dados_save['updated'] = date('Y-m-d H:i:s');
			$dados_save['image'] = $this->post->upload_media('capas', 'gif|png|jpg');
			// Identifica se é uma página ou uma postagem
			// 0=post, 1=Página, 2=Agenda
			$dados_save['page'] = '2';

			$new_post = $this->post->save($dados_save);

			if($new_post)
			{
				$this->session->set_flashdata('msg_sistema', 'Agenda salva com sucesso.');
				redirect('admin/agendas');
			} else {
				$this->session->set_flashdata('msg_sistema', 'Erro ao salvar a agenda.');
				redirect('admin/agendas');
			}
		}
	}

	public function edit($id = null)
	{
		$layout_vars = array();
		$content_vars = array();

		$this->form_validation->set_rules('title', 'Título', 'required');
		$this->form_validation->set_rules('created', 'Data', 'required');
		
		if ($this->form_validation->run() == FALSE)
		{

			if($id == null){
				$this->session->set_flashdata('msg_sistema', 'Agenda inexistente.');
				redirect('admin/posts');
			}

			$this->load->model('post_categoria');
			$this->load->model('categoria');
			$this->load->model('post');

			// Prepara a lista de categorias.
			$query = $this->categoria->get_list();
			$categorias = array();
			foreach($query->result() as $row){
				$categorias[$row->id] = $row->title;
			}

			// Prepara as categorias selecionadas.
			$query = $this->post_categoria->get_by_field('post_id', $id);
			$cat_select = array();
			foreach($query->result() as $x => $row){
				$cat_select[$x] = $row->category_id;
			}

			$content_vars['id'] = $id;
			$content_vars['categorias'] = $categorias;
			$content_vars['cat_select'] = $cat_select;
			$content_vars['row'] = $this->post->get_by_id($id)->row();
			
			$this->wpanel->load_view('agendas/edit', $content_vars);

		} else {

			$this->load->model('post');

			$dados_save = array();
			$dados_save['title'] = $this->input->post('title');
			$dados_save['description'] = $this->input->post('description');
			$dados_save['link'] = strtolower(url_title(convert_accented_characters($this->input->post('title'))));
			$dados_save['content'] = $this->input->post('content');
			$dados_save['tags'] = $this->input->post('tags');
			$dados_save['status'] = $this->input->post('status');
			$dados_save['created'] = datetime_for_mysql($this->input->post('created') . ' 12:00:00');//date('Y-m-d H:i:s', strtotime($this->input->post('created')));
			$dados_save['updated'] = date('Y-m-d H:i:s');
			// Identifica se é uma página ou uma postagem
			// 0=post, 1=Página, 2=Agenda
			$dados_save['page'] = '2';
			
			if($this->input->post('alterar_imagem')=='1')
			{
				$postagem = $this->post->get_by_id($id)->row();
				$this->post->remove_media('capas/' . $postagem->image);
				$dados_save['image'] = $this->post->upload_media('capas', 'gif|png|jpg');
			}

			$upd_post = $this->post->update($id, $dados_save);

			if($upd_post)
			{
				$this->session->set_flashdata('msg_sistema', 'Agenda salva com sucesso.');
				redirect('admin/agendas');
			} else {
				$this->session->set_flashdata('msg_sistema', 'Erro ao salvar a agenda.');
				redirect('admin/agendas');
			}
		}
	}

	public function delete($id = null)
	{

		if($id == null){
			$this->session->set_flashdata('msg_sistema', 'Agenda inexistente.');
			redirect('admin/agendas');
		}

		$this->load->model('post');

		$postagem = $this->post->get_by_id($id)->row();
		$this->post->remove_media('capas/' . $postagem->image);	

		if($this->post->delete($id)){
			$this->session->set_flashdata('msg_sistema', 'Agenda excluída com sucesso.');
			redirect('admin/agendas');
		} else {
			$this->session->set_flashdata('msg_sistema', 'Erro ao excluir a agenda.');
			redirect('admin/agendas');
		}
	}
}