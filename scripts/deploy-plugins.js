/**
 * PTGates 플러그인 자동 배포 스크립트
 * 
 * 생성된 플러그인 파일들을 SFTP로 서버에 자동 업로드
 */

const fs = require('fs');
const path = require('path');
const { Client } = require('ssh2');

// SFTP 설정 (.vscode/sftp.json에서 읽어옴)
const SFTP_CONFIG = {
    host: '82.180.173.17',
    port: 22,
    username: 'root',
    privateKeyPath: 'C:/Users/darkcloud/.ssh/id_ed25519',
    remotePath: '/var/www/ptgates/wp-content/plugins'
};

// 업로드할 플러그인 목록
const PLUGINS = [
    '0000-ptgates-platform',
    '1200-ptgates-quiz'
    // 다음 모듈 개발 시 추가
    // '4100-ptgates-reviewer',
    // ...
];

/**
 * 로컬 파일 시스템에서 재귀적으로 파일 목록 가져오기
 */
function getFiles(dir, fileList = []) {
    const files = fs.readdirSync(dir);
    
    files.forEach(file => {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);
        
        // node_modules, .git 등 제외
        if (file.startsWith('.') || file === 'node_modules') {
            return;
        }
        
        if (stat.isDirectory()) {
            getFiles(filePath, fileList);
        } else {
            fileList.push(filePath);
        }
    });
    
    return fileList;
}

/**
 * 디렉토리 생성 (재귀적)
 */
function ensureDirectory(sftp, remoteDir) {
    return new Promise((resolve) => {
        const dirs = remoteDir.split('/').filter(d => d);
        let currentPath = '';
        
        const createDir = (index) => {
            if (index >= dirs.length) {
                resolve();
                return;
            }
            
            currentPath += '/' + dirs[index];
            sftp.mkdir(currentPath, (err) => {
                // 이미 존재하면 무시
                createDir(index + 1);
            });
        };
        
        createDir(0);
    });
}

/**
 * SFTP를 통해 파일 업로드
 */
function uploadFiles(conn, localPath, remotePath) {
    return new Promise((resolve, reject) => {
        conn.sftp((err, sftp) => {
            if (err) {
                reject(err);
                return;
            }
            
            // 디렉토리 생성
            const remoteDir = path.dirname(remotePath).replace(/\\/g, '/');
            ensureDirectory(sftp, remoteDir).then(() => {
                // 파일 업로드
                const fileContent = fs.readFileSync(localPath);
                sftp.writeFile(remotePath, fileContent, (err) => {
                    if (err) {
                        reject(err);
                    } else {
                        resolve();
                    }
                });
            });
        });
    });
}

/**
 * 플러그인 업로드
 */
async function deployPlugin(pluginName) {
    const localPluginPath = path.join(__dirname, '..', 'wp-content', 'plugins', pluginName);
    const remotePluginPath = `${SFTP_CONFIG.remotePath}/${pluginName}`;
    
    if (!fs.existsSync(localPluginPath)) {
        console.log(`⚠️  ${pluginName} 플러그인을 찾을 수 없습니다.`);
        return;
    }
    
    console.log(`\n📦 ${pluginName} 업로드 시작...`);
    
    const files = getFiles(localPluginPath);
    const privateKey = fs.readFileSync(SFTP_CONFIG.privateKeyPath);
    
    const conn = new Client();
    
    await new Promise((resolve, reject) => {
        conn.on('ready', async () => {
            console.log('✓ SFTP 연결 성공');
            
            try {
                for (const file of files) {
                    const relativePath = path.relative(localPluginPath, file);
                    const remoteFile = `${remotePluginPath}/${relativePath.replace(/\\/g, '/')}`;
                    
                    // 디렉토리 생성
                    const remoteDir = path.dirname(remoteFile);
                    await uploadFiles(conn, file, remoteFile);
                    console.log(`  ✓ ${relativePath}`);
                }
                
                console.log(`✅ ${pluginName} 업로드 완료!`);
                conn.end();
                resolve();
            } catch (error) {
                console.error(`❌ 업로드 오류:`, error.message);
                conn.end();
                reject(error);
            }
        });
        
        conn.on('error', (err) => {
            console.error('❌ 연결 오류:', err.message);
            reject(err);
        });
        
        conn.connect({
            host: SFTP_CONFIG.host,
            port: SFTP_CONFIG.port,
            username: SFTP_CONFIG.username,
            privateKey: privateKey
        });
    });
}

/**
 * 메인 실행
 */
async function main() {
    console.log('🚀 PTGates 플러그인 자동 배포 시작\n');
    
    for (const plugin of PLUGINS) {
        try {
            await deployPlugin(plugin);
        } catch (error) {
            console.error(`❌ ${plugin} 업로드 실패:`, error.message);
        }
    }
    
    console.log('\n✅ 모든 플러그인 배포 완료!');
}

// 실행
if (require.main === module) {
    main().catch(console.error);
}

module.exports = { deployPlugin, PLUGINS };

