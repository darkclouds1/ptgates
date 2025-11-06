# PTGates 플러그인 자동 배포 가이드

## 방법 1: VSCode SFTP 확장 사용 (권장)

현재 `.vscode/sftp.json`에 설정이 되어 있고 `uploadOnSave: true`로 설정되어 있으므로:

1. **자동 업로드**: 파일을 저장하면 자동으로 서버에 업로드됩니다
2. **수동 업로드**: `Ctrl+Shift+P` → "SFTP: Upload" 선택

### SFTP 설정 확인
- 호스트: `82.180.173.17`
- 포트: `22`
- 사용자: `root`
- 키 파일: `C:/Users/darkcloud/.ssh/id_ed25519`
- 원격 경로: `/var/www/ptgates`

## 방법 2: Node.js 배포 스크립트 사용

### 설치
```bash
npm install ssh2
```

### 실행
```bash
node scripts/deploy-plugins.js
```

### 플러그인 목록 업데이트
`scripts/deploy-plugins.js` 파일의 `PLUGINS` 배열에 새 플러그인을 추가하면 자동으로 업로드됩니다.

## 방법 3: PowerShell 스크립트 (직접 SFTP)

```powershell
# WinSCP 또는 PSCP 사용
# 또는 PowerShell의 Invoke-WebRequest 사용
```

## 권장 워크플로우

1. **개발 중**: VSCode SFTP 확장의 자동 업로드 활용 (`uploadOnSave: true`)
2. **배포 시**: `scripts/deploy-plugins.js`로 일괄 업로드
3. **수동 업로드**: 필요한 경우 VSCode SFTP 확장 사용

## 주의사항

- ⚠️ 플러그인 파일만 업로드 (node_modules, .git 등 제외)
- ⚠️ 서버에 이미 존재하는 파일은 덮어쓰기 주의
- ⚠️ 배포 전 테스트 권장

