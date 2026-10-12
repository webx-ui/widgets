<?php

declare(strict_types=1);

namespace WebxUi\Widgets\Forms;

use Illuminate\Validation\Validator;

/**
 * Laravel's own validator, asked only for its words: the line it would write for a rule that
 * failed, with `:attribute`, `:min`, `:values` and the rest replaced — the custom messages and
 * attribute names of the form, `validation.custom.*` and `validation.attributes.*` of the site, in
 * the language of the page. So the browser says what the server would, from no dictionary of its
 * own (spec §17.5).
 *
 * The validator picks the line of a size rule (`min.string`, `min.file`) by the value it holds;
 * here there is no value yet, so the field says what it will be.
 *
 * @internal
 */
final class RuleWords extends Validator
{
    private string $kind = 'string';

    /**
     * @param  list<mixed>  $parameters
     * @param  string|null  $pattern  the rule's key when it has a `*` the attribute fills in
     */
    public function say(string $attribute, string $rule, array $parameters, string $kind, ?string $pattern = null): string
    {
        $this->kind = $kind;
        $this->implicitAttributes = $pattern !== null && $pattern !== $attribute ? [$pattern => [$attribute]] : [];

        return (string) $this->makeReplacements($this->getMessage($attribute, $rule), $attribute, $rule, $parameters);
    }

    protected function getAttributeType($attribute): string
    {
        return $this->kind;
    }
}
