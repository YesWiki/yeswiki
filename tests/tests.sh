phpunit --do-not-cache-result --stderr tests $1 \
    && (
        for foldername in `ls -d tools/*/tests`
        do
            phpunit --do-not-cache-result --stderr $foldername $1 || exit 1
        done
    )