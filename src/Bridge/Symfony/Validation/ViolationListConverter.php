<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Validation;

use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafTiersPrestations\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTiersPrestations\Domain\Validation\Violation;

/**
 * Turns the violations of the package into a Symfony {@see ConstraintViolationList}, the format understood by the
 * Form component (`ViolationMapper`), API Platform and most API error renderers.
 *
 * - property path: the violation path (`invoice.lines[0].quantity`), optionally prefixed;
 * - code: the stable violation code (`line.total_mismatch`);
 * - message template: the violation code, message: its translation in the domain {@see self::TRANSLATION_DOMAIN}
 *   when a translator is given, the English message of the violation otherwise;
 * - parameters: the scalar parameters of the violation as `%name%` placeholders;
 * - cause: the original {@see Violation}; root: none.
 *
 * The rejected raw values are never exposed (the invalid value is always null), like everywhere in the package.
 */
final readonly class ViolationListConverter
{
    public const TRANSLATION_DOMAIN = 'urssaf_tiers_prestations';

    /**
     * @param ?TranslatorInterface $translator null to keep the English messages of the violations
     */
    public function __construct(private ?TranslatorInterface $translator) {}

    /**
     * The errors (warnings left out).
     *
     * @param ValidationResult|ValidationFailed $source     the violations, or the exception that carries them
     * @param string                            $pathPrefix prepended to every path (for example the name of the form field holding the draft)
     */
    public function convert(ValidationResult|ValidationFailed $source, string $pathPrefix = ''): ConstraintViolationList
    {
        return $this->toList(self::result($source, $pathPrefix)->errors());
    }

    /**
     * The errors and the warnings, in the order they were reported.
     *
     * @param ValidationResult|ValidationFailed $source     the violations, or the exception that carries them
     * @param string                            $pathPrefix prepended to every path
     */
    public function convertWithWarnings(ValidationResult|ValidationFailed $source, string $pathPrefix = ''): ConstraintViolationList
    {
        return $this->toList(self::result($source, $pathPrefix)->all());
    }

    private static function result(ValidationResult|ValidationFailed $source, string $pathPrefix): ValidationResult
    {
        return ($source instanceof ValidationFailed ? $source->result() : $source)->withPathPrefix($pathPrefix);
    }

    /**
     * @param list<Violation> $violations
     */
    private function toList(array $violations): ConstraintViolationList
    {
        $list = new ConstraintViolationList();
        foreach ($violations as $violation) {
            $list->add($this->toConstraintViolation($violation));
        }

        return $list;
    }

    private function toConstraintViolation(Violation $violation): ConstraintViolation
    {
        $parameters = [];
        foreach ($violation->params() as $name => $value) {
            $parameters['%' . $name . '%'] = self::stringify($value);
        }
        $message = $this->translator?->trans($violation->code(), $parameters, self::TRANSLATION_DOMAIN) ?? $violation->message();

        return new ConstraintViolation(
            $message,
            $violation->code(),
            $parameters,
            null,
            $violation->path(),
            null,
            null,
            $violation->code(),
            null,
            $violation,
        );
    }

    private static function stringify(bool|float|int|string $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
