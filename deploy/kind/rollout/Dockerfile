# The image run.sh rolls out under writes: the one under test, with one more migration in its program.
ARG IMAGE
FROM ${IMAGE}
USER root
COPY 20261111180000_KindRollingUpdate.php /tmp/
RUN mv /tmp/20261111180000_KindRollingUpdate.php "$(echo /opt/yeswiki/program-*)/src/migrations/"
USER 10001:10001
