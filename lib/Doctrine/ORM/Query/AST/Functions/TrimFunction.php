<?php

declare(strict_types=1);

namespace Doctrine\ORM\Query\AST\Functions;

use Doctrine\DBAL\Platforms\TrimMode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;

use function assert;
use function strcasecmp;

/**
 * "TRIM" "(" [["LEADING" | "TRAILING" | "BOTH"] [char] "FROM"] StringPrimary ")"
 *
 * @link    www.doctrine-project.org
 */
class TrimFunction extends FunctionNode
{
    /** @var bool */
    public $leading;

    /** @var bool */
    public $trailing;

    /** @var bool */
    public $both;

    /** @var string|false */
    public $trimChar = false;

    /** @var Node */
    public $stringPrimary;

    /**
     * {@inheritdoc}
     */
    public function getSql(SqlWalker $sqlWalker)
    {
        $stringPrimary = $sqlWalker->walkStringPrimary($this->stringPrimary);
        $platform      = $sqlWalker->getConnection()->getDatabasePlatform();
        $trimMode      = $this->getTrimMode();

        if ($this->trimChar !== false) {
            return $platform->getTrimExpression(
                $stringPrimary,
                $trimMode,
                $platform->quoteStringLiteral($this->trimChar)
            );
        }

        return $platform->getTrimExpression($stringPrimary, $trimMode);
    }

    /**
     * {@inheritdoc}
     */
    public function parse(Parser $parser)
    {
        $lexer = $parser->getLexer();

        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        $this->parseTrimMode($parser);

        if ($lexer->isNextToken(Lexer::T_STRING)) {
            $parser->match(Lexer::T_STRING);

            assert($lexer->token !== null);
            $this->trimChar = $lexer->token->value;
        }

        if ($this->leading || $this->trailing || $this->both || $this->trimChar) {
            $parser->match(Lexer::T_FROM);
        }

        $this->stringPrimary = $parser->StringPrimary();

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    /** @psalm-return TrimMode::* */
    private function getTrimMode(): int
    {
        if ($this->leading) {
            return TrimMode::LEADING;
        }

        if ($this->trailing) {
            return TrimMode::TRAILING;
        }

        if ($this->both) {
            return TrimMode::BOTH;
        }

        return TrimMode::UNSPECIFIED;
    }

    private function parseTrimMode(Parser $parser): void
    {
        $lexer = $parser->getLexer();
        assert($lexer->lookahead !== null);
        $value = $lexer->lookahead->value;

        if (strcasecmp('leading', $value) === 0) {
            $parser->match(Lexer::T_LEADING);

            $this->leading = true;

            return;
        }

        if (strcasecmp('trailing', $value) === 0) {
            $parser->match(Lexer::T_TRAILING);

            $this->trailing = true;

            return;
        }

        if (strcasecmp('both', $value) === 0) {
            $parser->match(Lexer::T_BOTH);

            $this->both = true;

            return;
        }
    }
}
