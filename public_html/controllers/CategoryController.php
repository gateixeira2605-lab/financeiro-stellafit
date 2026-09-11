<?php
declare(strict_types=1);

final class CategoryController extends BaseController
{
    public function index(): void
    {
        $categories = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
        $this->render('categories/index', compact('categories') + ['pageTitle' => 'Categorias']);
    }

    public function form(): void
    {
        $category = ['id' => '', 'name' => '', 'type' => 'fixa', 'classification' => 'despesa_operacional'];
        if ($id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) {
            $stmt = db()->prepare('SELECT * FROM categories WHERE id=?'); $stmt->execute([$id]);
            $category = $stmt->fetch() ?: $category;
        }
        $this->render('categories/form', compact('category') + ['pageTitle' => $category['id'] ? 'Editar categoria' : 'Nova categoria']);
    }

    public function save(): void
    {
        verify_csrf();
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        $type = (string) ($_POST['type'] ?? '');
        $classification = (string) ($_POST['classification'] ?? '');
        $validClasses = ['despesa_operacional','despesa_administrativa','investimento','receita_operacional','receita_nao_operacional'];
        if ($name === '' || !in_array($type, ['fixa','variavel'], true) || !in_array($classification, $validClasses, true)) throw new InvalidArgumentException('Preencha os dados da categoria corretamente.');
        if ($id) {
            $stmt = db()->prepare('UPDATE categories SET name=?, type=?, classification=? WHERE id=?'); $stmt->execute([$name, $type, $classification, $id]);
        } else {
            $stmt = db()->prepare('INSERT INTO categories (name,type,classification) VALUES (?,?,?)'); $stmt->execute([$name, $type, $classification]);
        }
        flash('success', 'Categoria salva.'); redirect('categories');
    }

    public function delete(): void
    {
        verify_csrf();
        $stmt = db()->prepare('DELETE FROM categories WHERE id=?');
        try { $stmt->execute([(int) ($_POST['id'] ?? 0)]); flash('success', 'Categoria excluída.'); }
        catch (PDOException) { flash('error', 'Esta categoria está em uso e não pode ser excluída.'); }
        redirect('categories');
    }
}
