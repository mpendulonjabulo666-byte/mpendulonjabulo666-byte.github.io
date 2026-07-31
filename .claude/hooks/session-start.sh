#!/bin/bash
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

if ! command -v uv >/dev/null 2>&1; then
  curl -LsSf https://astral.sh/uv/install.sh | sh
  export PATH="$HOME/.local/bin:$PATH"
fi

if ! command -v graphify >/dev/null 2>&1; then
  uv tool install graphifyy >/dev/null 2>&1 || uv tool install graphifyy
fi

if ! command -v pipx >/dev/null 2>&1; then
  uv tool install pipx >/dev/null 2>&1 || uv tool install pipx
fi

if ! command -v agent-reach >/dev/null 2>&1; then
  pipx install "git+https://github.com/Panniantong/agent-reach.git" >/dev/null 2>&1 \
    || pipx install "git+https://github.com/Panniantong/agent-reach.git"
  # Zero-config channels only (web, YouTube, GitHub, RSS, Exa search, V2EX,
  # Bilibili basic) — cookie/login-based platforms (Twitter, Reddit, etc.)
  # are opt-in only, run "agent-reach install --channels=X" when asked for one.
  agent-reach install --env=auto >/dev/null 2>&1 || true
  mkdir -p "$HOME/.config/yt-dlp"
  grep -qxF -- '--js-runtimes node' "$HOME/.config/yt-dlp/config" 2>/dev/null \
    || echo '--js-runtimes node' >> "$HOME/.config/yt-dlp/config"
fi

TOOL_BIN="$(uv tool dir --bin 2>/dev/null || echo "$HOME/.local/bin")"
if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
  echo "export PATH=\"$TOOL_BIN:\$PATH\"" >> "$CLAUDE_ENV_FILE"
fi
