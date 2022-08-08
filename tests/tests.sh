phpunit --do-not-cache-result --stderr tests \
    && (
        for foldername in `ls -d tools/*/tests`
        do
            phpunit --do-not-cache-result --stderr $foldername || exit 1
        done
    )