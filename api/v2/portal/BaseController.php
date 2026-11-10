<?php

declare(strict_types=1);

namespace Portal;

/**
 * Shared helpers for all portal controllers: input validation and JSON responses.
 *
 * DB access belongs in injected model instances, not here.
 * Use Models\BaseModel::findById() and Models\BaseModel::paginate() from your model.
 */
abstract class BaseController
{
    /**
     * Explicit constructor so child classes can safely call parent::__construct().
     *
     * PHP 7.x throws "Cannot call constructor" when parent::__construct() is called
     * on a class without an explicit constructor — even an empty one fixes this.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Validate input or respond 422 with field errors.
     *
     * Returns type-coerced data (numeric → int, decimal → float, boolean → bool).
     *
     * @param array<string, mixed>    $data
     * @param array<int|string, mixed> $rules
     *
     * @return array<string, mixed>
     */
    protected function validate(array $data, array $rules): array
    {
        $validator = validator($data, $rules);
        if (!$validator->validate()) {
            response([
                'code'    => 422,
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ]);
        }

        return $validator->sanitize();
    }

    /**
     * Standard success response.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $extras
     */
    protected function success(string $message, array $data = [], array $extras = []): void
    {
        response(array_merge([
            'code'    => 200,
            'message' => $message,
            'data'    => $data,
        ], $extras));
    }
}
