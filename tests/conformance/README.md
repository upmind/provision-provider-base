# Validation conformance suite

This directory defines the expected behaviour of every validation rule that
data sets can use. Any validator implementation must pass every case.

`SupportedRules::RULES` lists the supported rules. Each supported rule has one
case file, `validation/<rule>.json`. `RuleParser` rejects any other rule.

## Case file format

`schema.json` is the JSON Schema of a case file.

```json
{
  "rule": "max",
  "cases": [
    {
      "name": "string at the maximum length",
      "rules": { "value": ["string", "max:3"] },
      "data": { "value": "abc" },
      "valid": true
    },
    {
      "name": "string over the maximum length",
      "rules": { "value": ["string", "max:3"] },
      "data": { "value": "abcd" },
      "valid": false,
      "failed": { "value": ["max"] }
    }
  ]
}
```

- `rule`: the canonical rule name. It is the same as the file name.
- `rules`: the rules of each field. Each value is a list of rule strings.
  Field names can use dot notation and `*` wildcards.
- `data`: the input data. A field that is not in `data` is missing.
- `valid`: whether the data passes all rules.
- `failed`: for invalid cases only. The rules that fail, per field. The order
  is not significant.

## Rule names

A rule string is `name` or `name:parameters`. The canonical name is snake case.
Validators must accept these aliases: `alpha-score`, `alpha-dash-dot` and
`domain-name`.

## Determinism

- Cases do not depend on the current date, the network, a database or files.
- Date cases use fixed dates.
- `regex` and `not_regex` patterns use PCRE syntax.
- `country_code` and `international_phone` depend on ISO 3166 and phone number
  metadata. Cases use stable values.

## Running the suite

PHPUnit runs every case against the Laravel validator:

```sh
vendor/bin/phpunit --filter ConformanceTest
```
