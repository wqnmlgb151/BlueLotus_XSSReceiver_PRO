# 项目文档

| 文档 | 用途 |
|---|---|
| [实施计划与验收](optimization-plan.md) | 问题依据、阶段结果与自检记录；最后一节为当前状态 |
| [备份、回滚与恢复](recovery-runbook.md) | 部署前备份和新旧 ID、AES、CSRF 配套回退 |
| [维护边界与依赖](maintenance-boundaries.md) | 组件版本、容量限制和隔离 Web 验证复现 |
| [性能原始数据](performance-baseline.json) | 本地合成数据单次测量，不是生产性能承诺 |
| [v4.2.0 发布说明](releases/v4.2.0.md) | 功能变化、升级要求与验证范围 |

源码目录：`app/` 为分层后端，根目录 PHP 为入口，`src/` 为维护源文件，`static/` 为实际运行资源，`tests/` 为隔离回归，`scripts/` 为构建工具。`PHPMailer/`、`guide/` 和 `diff/` 保持历史依赖及参考用途，不作本轮批量整理。

完整离线验证：`pwsh -File tests/run.ps1 -Php <PHP8可执行路径>`。JS 构建：`node scripts/build-panel.js`。源码包含测试和维护资料，部署 ZIP 只提供运行文件及文档；测试脚本应在源码副本运行。

从已提交修订构建部署包：`pwsh -File scripts/package-release.ps1 -Version 4.2.0`。产物位于忽略的 dist/，脚本拒绝覆盖已有包并检查不含实际配置和采集数据。

历史执行记录中的 `<本地证据根目录>` 是维护者私有证据位置占位符，不是发行包路径。真实配置、业务数据、备份和浏览器证据均不提交到仓库。部署前请先阅读恢复步骤。
