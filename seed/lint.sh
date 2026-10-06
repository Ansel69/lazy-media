cd /var/www/html/wp-content/themes/lazymedia || exit 1
fail=0
for f in $(find . -name '*.php'); do
  out=$(php -l "$f" 2>&1)
  case "$out" in
    "No syntax errors"*) ;;
    *) echo "THEME FAIL: $out"; fail=1 ;;
  esac
done
[ "$fail" = "0" ] && echo "THEME_LINT_OK"

cd /seed || exit 1
fail=0
for f in *.php; do
  out=$(php -l "$f" 2>&1)
  case "$out" in
    "No syntax errors"*) ;;
    *) echo "SEED FAIL: $out"; fail=1 ;;
  esac
done
[ "$fail" = "0" ] && echo "SEED_LINT_OK"