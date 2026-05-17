FROM postgres:17

RUN apt-get update && apt-get install -y --no-install-recommends \
        hunspell-pl \
    && rm -rf /var/lib/apt/lists/* \
    && iconv -f ISO-8859-2 -t UTF-8//TRANSLIT /usr/share/hunspell/pl_PL.dic \
        > /usr/share/postgresql/17/tsearch_data/polish.dict \
    && iconv -f ISO-8859-2 -t UTF-8//TRANSLIT /usr/share/hunspell/pl_PL.aff \
        > /usr/share/postgresql/17/tsearch_data/polish.affix \
    && sed -i -E 's/SET (ISO-?8859-2|ISO8859-2|CP1250|WINDOWS-1250)/SET UTF-8/i' \
        /usr/share/postgresql/17/tsearch_data/polish.affix

COPY init-polish-fts.sql /docker-entrypoint-initdb.d/20-init-polish-fts.sql
