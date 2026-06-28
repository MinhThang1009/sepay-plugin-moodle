// Cấu hình commitlint — ép Conventional Commits (https://www.conventionalcommits.org).
// Dùng bởi .github/workflows/commitlint.yml. Subject tiếng Việt vẫn hợp lệ (chỉ kiểm type/format).
module.exports = {
  extends: ['@commitlint/config-conventional'],
};
