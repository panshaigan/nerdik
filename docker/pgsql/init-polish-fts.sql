-- Polish full-text search catalog (runs once on first Postgres volume init).
-- Requires hunspell-pl dictionaries baked into docker/pgsql/Dockerfile.

CREATE EXTENSION IF NOT EXISTS unaccent;

CREATE TEXT SEARCH DICTIONARY polish_ispell (
    TEMPLATE = ispell,
    DictFile = polish,
    AffFile = polish
);

CREATE TEXT SEARCH DICTIONARY polish_unaccent (
    TEMPLATE = unaccent,
    Rules = unaccent
);

CREATE TEXT SEARCH CONFIGURATION polish (COPY = simple);

ALTER TEXT SEARCH CONFIGURATION polish
    ALTER MAPPING FOR asciiword, asciihword, hword_asciipart,
                      word, hword, hword_part
    WITH polish_ispell, polish_unaccent, simple;
