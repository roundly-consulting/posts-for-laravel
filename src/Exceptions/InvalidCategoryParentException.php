<?php

declare(strict_types=1);

namespace RoundlyConsulting\Posts\Exceptions;

use RoundlyConsulting\Posts\Models\Category;

/**
 * The parent would make the category hierarchy loop: the category itself, or one of its own
 * descendants. Thrown before anything is saved.
 */
final class InvalidCategoryParentException extends PostsException
{
    public static function wouldCycle(Category $category, int|string $parentId): self
    {
        $key = $category->getKey();
        $id = is_int($key) || is_string($key) ? $key : '?';

        return new self(
            "Category [{$parentId}] cannot be the parent of category [{$id}]: the hierarchy would loop.",
        );
    }
}
