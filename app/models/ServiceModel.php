<?php
declare(strict_types=1);

namespace KronoConnect\Models;

class ServiceModel extends BaseModel
{
    protected string $table = 'services';

    public function getAllOrdered(): array
    {
        $t = $this->db->t($this->table);
        return $this->db->fetchAll("SELECT * FROM `{$t}` ORDER BY parent_id ASC, order_index ASC, name ASC");
    }

    public function getTree(): array
    {
        $all = $this->getAllOrdered();
        return $this->buildTree($all);
    }

    private function buildTree(array $elements, ?int $parentId = null): array
    {
        $branch = [];
        foreach ($elements as $element) {
            $currentParentId = $element['parent_id'] !== null ? (int)$element['parent_id'] : null;
            if ($currentParentId === $parentId) {
                $children = $this->buildTree($elements, (int)$element['id']);
                if ($children) {
                    $element['children'] = $children;
                } else {
                    $element['children'] = [];
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }

    public function create(string $name, ?int $parentId = null, ?string $description = null): int
    {
        return $this->db->insert($this->table, [
            'name' => $name,
            'parent_id' => $parentId,
            'description' => $description,
            'order_index' => 0
        ]);
    }

    public function updateInfo(int $id, string $name, ?int $parentId): void
    {
        $this->db->update($this->table, [
            'name' => $name,
            'parent_id' => $parentId
        ], ['id' => $id]);
    }

    public function updateOrder(int $id, ?int $parentId, int $orderIndex): void
    {
        $this->db->update($this->table, [
            'parent_id' => $parentId,
            'order_index' => $orderIndex
        ], ['id' => $id]);
    }

    public function deleteService(int $id): void
    {
        $this->db->delete($this->table, ['id' => $id]);
    }
}
