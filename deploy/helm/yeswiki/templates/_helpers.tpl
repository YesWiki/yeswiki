{{/* Names, labels and the pod pieces the Deployment and the two Jobs share. */}}
{{- define "yeswiki.fullname" -}}
{{- if contains .Chart.Name .Release.Name -}}
{{- .Release.Name | trunc 63 | trimSuffix "-" -}}
{{- else -}}
{{- printf "%s-%s" .Release.Name .Chart.Name | trunc 63 | trimSuffix "-" -}}
{{- end -}}
{{- end -}}

{{- define "yeswiki.selectorLabels" -}}
app.kubernetes.io/name: {{ .Chart.Name }}
app.kubernetes.io/instance: {{ .Release.Name }}
{{- end -}}

{{- define "yeswiki.labels" -}}
{{ include "yeswiki.selectorLabels" . }}
app.kubernetes.io/version: {{ .Chart.AppVersion | quote }}
app.kubernetes.io/managed-by: {{ .Release.Service }}
helm.sh/chart: {{ printf "%s-%s" .Chart.Name .Chart.Version }}
{{- end -}}

{{- define "yeswiki.image" -}}
{{ .Values.image.repository }}:{{ .Values.image.tag | default .Chart.AppVersion }}
{{- end -}}

{{- define "yeswiki.secretName" -}}
{{ .Values.existingSecret | default (include "yeswiki.fullname" .) }}
{{- end -}}

{{- define "yeswiki.required" -}}
{{- $_ := required "baseUrl is required: the address the wiki answers on" .Values.baseUrl -}}
{{- $_ := required "database.host is required: the chart ships no database" .Values.database.host -}}
{{- $_ := required "storage.bucket is required: every replica serves the same files" .Values.storage.bucket -}}
{{- $_ := required "storage.publicUrl is required: public files are read from the bucket" .Values.storage.publicUrl -}}
{{- if eq .Values.database.driver "sqlite" -}}
{{- fail "database.driver sqlite is one file on one machine: use mysql or pgsql" -}}
{{- end -}}
{{- end -}}

{{- define "yeswiki.env" -}}
- name: YESWIKI_REPLICATED
  value: "1"
- name: YESWIKI_QUEUE_CONSUMER
  value: worker
- name: YESWIKI_WORKERS
  value: {{ .Values.workers | quote }}
- name: BASE_URL
  value: {{ .Values.baseUrl | quote }}
- name: REWRITE_MODE
  value: {{ .Values.rewriteMode | ternary "true" "false" | quote }}
- name: TRUSTED_PROXIES
  value: {{ .Values.trustedProxies | quote }}
- name: ACTIVE_EXTENSIONS
  value: {{ .Values.activeExtensions | toJson | quote }}
- name: DB_DRIVER
  value: {{ .Values.database.driver | quote }}
- name: DB_HOST
  value: {{ .Values.database.host | quote }}
{{- with .Values.database.port }}
- name: DB_PORT
  value: {{ . | quote }}
{{- end }}
- name: DB_DATABASE
  value: {{ .Values.database.name | quote }}
- name: DB_USER
  value: {{ .Values.database.user | quote }}
- name: TABLE_PREFIX
  value: {{ .Values.database.tablePrefix | quote }}
- name: YESWIKI_STORAGE
  value: s3
- name: YESWIKI_S3_BUCKET
  value: {{ .Values.storage.bucket | quote }}
- name: YESWIKI_S3_REGION
  value: {{ .Values.storage.region | quote }}
- name: YESWIKI_S3_ENDPOINT
  value: {{ .Values.storage.endpoint | quote }}
- name: YESWIKI_S3_PUBLIC_URL
  value: {{ .Values.storage.publicUrl | quote }}
- name: YESWIKI_S3_PATH_STYLE
  value: {{ ternary "1" "0" .Values.storage.pathStyle | quote }}
{{- with .Values.storage.prefix }}
- name: YESWIKI_S3_PREFIX
  value: {{ . | quote }}
{{- end }}
{{- range $name, $value := .Values.env }}
- name: {{ $name }}
  value: {{ $value | quote }}
{{- end }}
{{- end -}}

{{- define "yeswiki.podSecurityContext" -}}
runAsNonRoot: true
runAsUser: 10001
runAsGroup: 10001
fsGroup: 10001
seccompProfile:
  type: RuntimeDefault
{{- end -}}

{{- define "yeswiki.containerSecurityContext" -}}
readOnlyRootFilesystem: true
allowPrivilegeEscalation: false
capabilities:
  drop: ["ALL"]
{{- end -}}

{{/* The wiki folders a pod writes to, each an emptyDir over the image's /srv/wiki, which keeps the entry points and the custom/ an operator shipped. */}}
{{- define "yeswiki.writableMounts" -}}
- name: cache
  mountPath: /srv/wiki/cache
- name: files
  mountPath: /srv/wiki/files
- name: private
  mountPath: /srv/wiki/private
{{- end -}}

{{- define "yeswiki.writableVolumes" -}}
- name: cache
  emptyDir: {}
- name: files
  emptyDir: {}
- name: private
  emptyDir: {}
{{- end -}}
