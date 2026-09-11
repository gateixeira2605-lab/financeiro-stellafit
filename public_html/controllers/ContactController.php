<?php
declare(strict_types=1);

final class ContactController extends BaseController
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $type = (string) ($_GET['type'] ?? '');
        $sql = 'SELECT * FROM contacts WHERE name LIKE ?'; $params = ['%' . $q . '%'];
        if (in_array($type, ['fornecedor','cliente','ambos'], true)) { $sql .= ' AND type=?'; $params[] = $type; }
        $sql .= ' ORDER BY name';
        $stmt = db()->prepare($sql); $stmt->execute($params); $contacts = $stmt->fetchAll();
        $this->render('contacts/index', compact('contacts', 'q', 'type') + ['pageTitle' => 'Contatos']);
    }

    public function form(): void
    {
        $contact = ['id'=>'','name'=>'','document'=>'','phone'=>'','email'=>'','type'=>'fornecedor','notes'=>''];
        if ($id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) { $stmt=db()->prepare('SELECT * FROM contacts WHERE id=?'); $stmt->execute([$id]); $contact=$stmt->fetch() ?: $contact; }
        $this->render('contacts/form', compact('contact') + ['pageTitle' => $contact['id'] ? 'Editar contato' : 'Novo contato']);
    }

    public function save(): void
    {
        verify_csrf();
        $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT); $name=trim((string)($_POST['name']??'')); $type=(string)($_POST['type']??'');
        if ($name==='' || !in_array($type,['fornecedor','cliente','ambos'],true)) throw new InvalidArgumentException('Nome e tipo são obrigatórios.');
        $values=[$name,trim((string)($_POST['document']??'')),trim((string)($_POST['phone']??'')),trim((string)($_POST['email']??'')),$type,trim((string)($_POST['notes']??''))];
        if ($id) { $values[]=$id; $stmt=db()->prepare('UPDATE contacts SET name=?,document=?,phone=?,email=?,type=?,notes=? WHERE id=?'); }
        else $stmt=db()->prepare('INSERT INTO contacts (name,document,phone,email,type,notes) VALUES (?,?,?,?,?,?)');
        $stmt->execute($values); flash('success','Contato salvo.'); redirect('contacts');
    }

    public function delete(): void
    {
        verify_csrf();
        try { $stmt=db()->prepare('DELETE FROM contacts WHERE id=?'); $stmt->execute([(int)($_POST['id']??0)]); flash('success','Contato excluído.'); }
        catch (PDOException) { flash('error','Este contato está em uso e não pode ser excluído.'); }
        redirect('contacts');
    }
}
