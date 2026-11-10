# syntax=docker/dockerfile:1
# The production image: the release's static binary, its program written out at build time, run by a non-root user on a read-only root.
ARG ALPINE_VERSION=3.24

FROM alpine:${ALPINE_VERSION} AS prepare
ARG TARGETARCH
ARG EXTENSIONS=""
COPY binary/dist/yeswiki-linux-* /dist/
COPY --chmod=0755 docker/image-entrypoint.sh /usr/local/bin/yeswiki-entrypoint
RUN set -eu; \
    case "$TARGETARCH" in \
        amd64) arch=x86_64 ;; \
        arm64) arch=aarch64 ;; \
        *) echo "no yeswiki binary for $TARGETARCH" >&2; exit 1 ;; \
    esac; \
    install -m 0755 "/dist/yeswiki-linux-$arch" /usr/local/bin/yeswiki; \
    YESWIKI_PROGRAM_ROOT=/opt/yeswiki HOME=/nonexistent /usr/local/bin/yeswiki prepare /srv/wiki; \
    if [ -n "$EXTENSIONS" ]; then \
        PATH="/usr/local/bin:$PATH" HOME=/nonexistent /usr/local/bin/yeswiki-entrypoint bundle $EXTENSIONS; \
    fi

FROM alpine:${ALPINE_VERSION}
RUN apk add --no-cache ca-certificates tzdata \
    && addgroup -S -g 10001 yeswiki \
    && adduser -S -D -H -u 10001 -G yeswiki -h /srv/wiki -s /sbin/nologin yeswiki
COPY --from=prepare /usr/local/bin/yeswiki /usr/local/bin/yeswiki
COPY --from=prepare /opt/yeswiki /opt/yeswiki
COPY --from=prepare --chown=yeswiki:yeswiki /srv/wiki /srv/wiki
COPY --chmod=0755 docker/image-entrypoint.sh /usr/local/bin/yeswiki-entrypoint

ENV YESWIKI_PROGRAM_ROOT=/opt/yeswiki \
    HOME=/srv/wiki \
    XDG_CONFIG_HOME=/tmp/caddy \
    XDG_DATA_HOME=/tmp/caddy \
    PORT=8080

USER 10001:10001
WORKDIR /srv/wiki
VOLUME /srv/wiki
EXPOSE 8080
STOPSIGNAL SIGTERM
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s CMD wget -q -O /dev/null "http://127.0.0.1:${PORT}/healthz" || exit 1

ENTRYPOINT ["/usr/local/bin/yeswiki-entrypoint"]
CMD ["serve"]
