# Tent: validate TENT_ACCESS_LOG_FORMAT (presets only).
case "${TENT_ACCESS_LOG_FORMAT:-}" in
  ""|safe) export TENT_ACCESS_LOG_FORMAT=safe ;;
  combined) export TENT_ACCESS_LOG_FORMAT ;;
  *) echo "TENT_ACCESS_LOG_FORMAT: invalid preset '${TENT_ACCESS_LOG_FORMAT}' (accepted: safe, combined)" >&2; exit 1 ;;
esac
