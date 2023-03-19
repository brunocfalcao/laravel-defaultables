<?php

namespace Brunocfalcao\Defaultables\Concerns;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Relations\Relation;

trait ConcernsFindingRelationships
{
    public function relationships(bool $showLocalKeys = false, bool $showForeignKeys = false): array
    {
        if (!($this instanceof Model)) {
            throw new InvalidArgumentException("This trait can only be used with an Eloquent Model instance.");
        }

        $modelClassPath = (new \ReflectionClass($this))->getFileName();
        $modelSourceCode = file_get_contents($modelClassPath);
        $modelNamespace = (new \ReflectionClass($this))->getNamespaceName();

        $pattern = '/function\s+([a-zA-Z0-9_]+)\s*\(\s*\)\s*(?::\s*(\w+[\\\]*\w+)\s*)?\{?\s*\s*return\s+\$this->(hasOne|hasMany|belongsTo|belongsToMany|morphTo|morphOne|morphMany|morphToMany|morphedByMany)\s*\(\s*([\w\\\\]+)::class/';
        preg_match_all($pattern, $modelSourceCode, $matches, PREG_SET_ORDER);

        $relationships = [];

        foreach ($matches as $match) {
            $relatedClass = $modelNamespace . '\\' . $match[4];
            if (!class_exists($relatedClass)) {
                $relatedClass = $match[4];
            }
            $relationship = $this->{$match[1]}();

            $relationshipData = [
                'name' => $match[3],
                'class' => $relatedClass,
            ];

            if ($relationship instanceof Relation) {
                if ($showForeignKeys || $showLocalKeys) {
                    if (method_exists($relationship, 'getForeignKeyName')) {
                        $relationshipData['foreignKey'] = $relationship->getForeignKeyName();
                    } elseif (method_exists($relationship, 'getQualifiedRelatedPivotKeyName')) {
                        $keyName = $relationship->getQualifiedRelatedPivotKeyName();
                        $relationshipData['foreignKey'] = preg_replace('/^.+\./', '', $keyName);
                    }

                    if (method_exists($relationship, 'getLocalKeyName')) {
                        $relationshipData['localKey'] = $relationship->getLocalKeyName();
                    } elseif (method_exists($relationship, 'getQualifiedParentKeyName')) {
                        $keyName = $relationship->getQualifiedParentKeyName();
                        $relationshipData['localKey'] = preg_replace('/^.+\./', '', $keyName);
                    }
                }

                // Determine the exact column on the current model instance for the given relationship
                if ($relationship instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                    $relationshipData['column'] = $relationship->getForeignKeyName();
                } elseif ($relationship instanceof \Illuminate\Database\Eloquent\Relations\HasOne || $relationship instanceof \Illuminate\Database\Eloquent\Relations\HasMany) {
                    $relationshipData['column'] = $relationship->getLocalKeyName();
                }
            }

            $relationships[$match[1]] = $relationshipData;
        }

        return $relationships;
    }
}
