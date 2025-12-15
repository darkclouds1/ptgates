
// app.js 패키지 설치 안내
// ============================================================================================================ 
// 1) 설치 명령어
// 아래 명령어를 터미널(명령 프롬프트)에서 입력하여 필요한 패키지를 설치합니다.
//
//     npm install express request ejs hashmap
//
// 2)설치 확인
// 설치가 완료되면, package.json 파일의 dependencies 항목에 위 패키지들이 추가된 것을 확인할 수 있습니다.
//
//     "dependencies": {
//         "express": "^5.x.x",
//         "request": "^2.x.x",
//         "ejs": "^3.x.x",
//         "hashmap": "^2.x.x"
//     }
//
// 3) 실행 테스트 진행
// ============================================================================================================ 

const express = require("express"); 
const app = express();
const crypto = require('crypto'); 
const bodyParser = require("body-parser");
const request = require('request');

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({extended : true}));

app.set("views" , __dirname+"/views");
app.set("views engline" , "ejs");
app.engine("html", require("ejs").renderFile);

app.use(express.static("views")); 
const getUrl = require('./properties');

var HashMap = require ('hashmap');


app.get("/" , (req,res) =>{
    const P_MID = "INIpayTest";                    // 상점아이디
    const P_OID = P_MID + "_" + Date.now();        // 가맹점 주문번호(가맹점에서 직접 설정)
    const P_AMT = "1000";                          // 결제금액
    const P_TIMESTAMP = Date.now();     
    const HashKey = "3CB8183A4BE283555ACC8363C0360223";          
    
    // BASE64_ENCODE(SHA512(P_AMT+P_OID+P_TIMESTAMP+HashKey))
    const data = P_AMT + P_OID + P_TIMESTAMP + HashKey;
    const hash = crypto.createHash('sha512')
                        .update(data, 'utf8')
                        .digest('base64');
    
    res.render("INImobile_mo_req.html", {
        P_MID: P_MID,
        P_OID: P_OID,
        P_AMT: P_AMT,
        P_TIMESTAMP: P_TIMESTAMP,
        P_CHKFAKE: hash 
    });
});


app.post("/INImobile_mo_return.ejs" , (req , res) => {
    //인증 결과 성공 시
    if(req.body.P_STATUS === "00"){    
        
        const P_STATUS = req.body.P_STATUS;         // 결과코드
        const P_RMESG1 = req.body.P_RMESG1;         // 결과메시지
        const P_TID = req.body.P_TID;               // 인증거래번호(성공시에만 전달)
        const P_AMT = req.body.P_AMT;               // 거래금액
        const P_NOTI = req.body.P_NOTI;             // 가맹점 임의 데이터
        
        //결제 승인 요청 
        let options = { 
                P_MID : P_TID.substring(10, 20),    //상점 아이디 설정 : 결제요청 페이지에서 사용한 MID값과 동일하게 세팅
                P_TID : P_TID,                    
        };
    
        //##########################################################################
		// 승인요청 API url (P_REQ_URL) 리스트 는 properties 에 세팅하여 사용합니다.
		// idc_name 으로 수신 받은 센터 네임을 properties 에서 include 하여 승인요청하시면 됩니다.
		//##########################################################################

        const idc_name = req.body.idc_name;     
        const P_REQ_URL = req.body.P_REQ_URL;       // 승인요청 URL
        const P_REQ_URL2 = getUrl.getAuthUrl(idc_name)

        if(P_REQ_URL == P_REQ_URL2) {

            request.post({method: 'POST', uri: P_REQ_URL2, form: options}, (err,httpResponse,body) =>{ 
                
                try{
    
                    let values = [];
                    values = new String(body).split("&"); 
                    console.log(values)
                    var map = new HashMap();
                    for( let x = 0; x < values.length; x++ ) {
                            
                        // 승인결과를 파싱값 잘라 hashmap에 저장
                        let i = values[x].indexOf("=");
                        let key1 = values[x].substring(0, i);
                        let value1 = values[x].substring(i+1);
                        map.set(key1, value1);
                        
                    }

                    res.render('INImobile_mo_return.ejs',{
                    P_STATUS :  map.get("P_STATUS"),
                    P_RMESG1 :  map.get("P_RMESG1"),
                    P_TID :  map.get("P_TID"),
                    P_TYPE :  map.get("P_TYPE"),
                    P_OID :  map.get("P_OID"),
                    P_AMT :  map.get("P_AMT"),
                    P_AUTH_DT :  map.get("P_AUTH_DT"),
                    P_VACT_NUM :  map.get("P_VACT_NUM")
                    }) 
                }catch(e){
                    /*
                        가맹점에서 승인결과 전문 처리 중 예외발생 시 망취소 요청할 수 있습니다.
                        승인요청 전문과 동일한 스펙으로 진행되며, 인증결과 수신 시 전달받은 "{인증결과 전달된 P_REQ_URL의 HOST}/smart/payNetCancel.ini" 로 망취소요청합니다.
        
                        ** 망취소를 일반 결제취소 용도로 사용하지 마십시오.
                        일반 결제취소는 INIAPI 취소/환불 서비스를 통해 진행해주시기 바랍니다.
                    */
                    
                    let options2 = { 
                        P_TID : req.body.P_TID,
                        P_MID : req.body.P_TID.substring(10, 20),
                        P_AMT : req.body.P_AMT,
                        P_OID : req.body.P_NOTI              
                    };

                    request.post({method: 'POST', uri: P_REQ_URL2.substring(0, 27)+"/smart/payNetCancel.ini", form: options2, json: true}, (err,httpResponse,body) =>{
                        let result = (err) ? err : JSON.stringify(body);
                        
                        console.log("<p>"+result+"</p>");
                            
                    });   
                }
            });
        }
    }else{

        res.render('INImobile_mo_return.ejs',{
            P_STATUS : req.body.P_STATUS,
            P_RMESG1 : req.body.P_RMESG1,
            P_TID :  req.body.P_TID,
            P_TYPE :  req.body.P_TYPE,
            P_OID :  req.body.P_OID,
            P_AMT :  req.body.P_AMT,
            P_AUTH_DT :  req.body.P_AUTH_DT,
            P_VACT_NUM :  req.body.P_VACT_NUM
        }) 
    }
});

app.listen(3000 , (err) =>{
    if(err) return console.log(err);
    console.log("The server is listening on port 3000");
});