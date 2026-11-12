# The image run.sh rolls out under writes: the one under test, with one more migration in its program, and a theme and a template override shipped in custom/ as an operator does.
ARG IMAGE
FROM ${IMAGE}
USER root
COPY 99991231235959_KindRollingUpdate.php /tmp/
COPY keywords.twig /srv/wiki/custom/templates/core/admin/keywords.twig
RUN program="$(echo /opt/yeswiki/program-*)" \
    && mv /tmp/99991231235959_KindRollingUpdate.php "$program/src/migrations/" \
    && mkdir -p /srv/wiki/custom/themes \
    && cp -r "$program/themes/yeswiki" /srv/wiki/custom/themes/kindtheme \
    && chown -R 10001:10001 /srv/wiki/custom
USER 10001:10001
