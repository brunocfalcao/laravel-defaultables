<?php

namespace Brunocfalcao\Defaultables;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class DefaultablesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
    }

    public function register()
    {
        $this->registerEvents();
    }

    protected function registerEvents()
    {
        /**
         * This event listen will monitor the eloquent saving to verify if
         * there are default eloquent attributes that we should compute.
         *
         * To be used, the eloquent model should have a default<ColumnName>
         * method. As example:
         *
         * public function defaultAddress(){
         *     return 'Lisbon';
         * };
         *
         * REMARK: The method needs to be public!
         *
         * What happens is during the eloquent.saving event (triggered by
         * the Laravel framework) we will intersect it with a listener so
         * we can scan the model methods, and detect what default values
         * should be called upon. In the example above, it will return
         * $this->address = $this->defaultAddress();
         *
         * Finally, good to remember that if you populate the attribute
         * in your observer method, this event will not change that
         * value. Only if the attribute is blank().
         */
        Event::listen('eloquent.saving: *', function (string $eventName, array $data) {
            foreach ($data as $model) {
                /**
                 * Check the methods list, and verify if there is a method
                 * that starts with default<xxx>.
                 */
                $defaults = collect(get_class_methods($model))->reject(function ($methodName) {
                    return ! (Str::of($methodName)
                                ->startsWith('default') &&
                            Str::of($methodName)
                                ->endsWith('Attribute'));
                });

                if ($defaults->count()) {
                    foreach ($defaults as $method) {
                        $attribute = strtolower(Str::headline(substr($method, 7, -9)));

                        // Compute the attribute name from the method.
                        $attribute = str_replace(' ', '_', $attribute);

                        if (blank($model->$attribute)) {
                            // Check if the attribute is not part of the hidden[] collection.
                            $reflection = new \ReflectionClass($model);
                            $property = $reflection->getProperty('hidden');
                            $property->setAccessible(true);

                            // This will be a collection of values from $hidden[].
                            $hidden = collect($property->getValue($model));

                            if (! $hidden->contains($attribute)) {
                                // Assign computed default value.
                                $model->$attribute = $model->$method();
                            }
                        }
                    }
                }
            }
        });
    }
}
