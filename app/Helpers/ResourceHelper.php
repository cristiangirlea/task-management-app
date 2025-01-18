<?php

namespace App\Helpers;

use Illuminate\Http\Resources\Json\JsonResource;

class ResourceHelper
{
    /**
     * Return a single resource instance.
     *
     * @param  string  $resourceClass
     * @param  mixed   $model
     * @return JsonResource
     */
    public static function item(string $resourceClass, $model)
    {
        if (!is_subclass_of($resourceClass, JsonResource::class)) {
            throw new \InvalidArgumentException("{$resourceClass} must extend " . JsonResource::class);
        }

        return new $resourceClass($model);
    }

    /**
     * Return a resource collection.
     *
     * @param  string  $resourceClass
     * @param  mixed   $collection
     * @return JsonResource
     */
    public static function collection(string $resourceClass, $collection)
    {
        if (!is_subclass_of($resourceClass, JsonResource::class)) {
            throw new \InvalidArgumentException("{$resourceClass} must extend " . JsonResource::class);
        }

        return $resourceClass::collection($collection);
    }
}
