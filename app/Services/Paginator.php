<?php

namespace App\Services;

class Paginator
{
    private int $totalItems;
    private int $perPage;
    private int $currentPage;
    private int $totalPages;
    private array $items;

    public function __construct(array $items, int $totalItems, int $perPage = 20, int $currentPage = 1)
    {
        $this->items = $items;
        $this->totalItems = $totalItems;
        $this->perPage = $perPage;
        $this->currentPage = max(1, $currentPage);
        $this->totalPages = (int) ceil($totalItems / $perPage);
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function getPreviousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    public function getNextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function getStartItem(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }
        return $this->getOffset() + 1;
    }

    public function getEndItem(): int
    {
        return min($this->getOffset() + $this->perPage, $this->totalItems);
    }

    public function getLinks(int $range = 2): array
    {
        $links = [];
        
        // Previous link
        if ($this->hasPreviousPage()) {
            $links[] = [
                'page' => $this->getPreviousPage(),
                'label' => '&laquo;',
                'active' => false
            ];
        }
        
        // First page
        if ($this->currentPage > $range + 1) {
            $links[] = [
                'page' => 1,
                'label' => '1',
                'active' => false
            ];
            
            if ($this->currentPage > $range + 2) {
                $links[] = [
                    'page' => null,
                    'label' => '...',
                    'active' => false
                ];
            }
        }
        
        // Page range
        for ($i = max(1, $this->currentPage - $range); $i <= min($this->totalPages, $this->currentPage + $range); $i++) {
            $links[] = [
                'page' => $i,
                'label' => (string) $i,
                'active' => $i === $this->currentPage
            ];
        }
        
        // Last page
        if ($this->currentPage < $this->totalPages - $range) {
            if ($this->currentPage < $this->totalPages - $range - 1) {
                $links[] = [
                    'page' => null,
                    'label' => '...',
                    'active' => false
                ];
            }
            
            $links[] = [
                'page' => $this->totalPages,
                'label' => (string) $this->totalPages,
                'active' => false
            ];
        }
        
        // Next link
        if ($this->hasNextPage()) {
            $links[] = [
                'page' => $this->getNextPage(),
                'label' => '&raquo;',
                'active' => false
            ];
        }
        
        return $links;
    }

    public function render(string $baseUrl, array $queryParams = []): string
    {
        if ($this->totalPages <= 1) {
            return '';
        }
        
        $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';
        
        foreach ($this->getLinks() as $link) {
            if ($link['page'] === null) {
                $html .= '<li class="page-item disabled"><span class="page-link">' . $link['label'] . '</span></li>';
            } else {
                $params = array_merge($queryParams, ['page' => $link['page']]);
                $url = $baseUrl . '?' . http_build_query($params);
                $activeClass = $link['active'] ? ' active' : '';
                $html .= '<li class="page-item' . $activeClass . '"><a class="page-link" href="' . $url . '">' . $link['label'] . '</a></li>';
            }
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }

    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->totalItems,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'has_previous' => $this->hasPreviousPage(),
            'has_next' => $this->hasNextPage(),
            'previous_page' => $this->getPreviousPage(),
            'next_page' => $this->getNextPage(),
            'start_item' => $this->getStartItem(),
            'end_item' => $this->getEndItem()
        ];
    }

    public static function paginate(array $items, int $perPage = 20, int $currentPage = 1): self
    {
        $totalItems = count($items);
        $offset = ($currentPage - 1) * $perPage;
        $pageItems = array_slice($items, $offset, $perPage);
        
        return new self($pageItems, $totalItems, $perPage, $currentPage);
    }
}
