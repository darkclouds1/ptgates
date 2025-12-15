<?php
    require_once('libs/properties.php');
    $prop = new properties();
 
     $P_STATUS    = $_REQUEST["P_STATUS"];
     $P_RMESG1    = $_REQUEST["P_RMESG1"];
     $P_TID       = $_REQUEST["P_TID"];
     $P_REQ_URL   = $_REQUEST["P_REQ_URL"];
     $P_NOTI      = $_REQUEST["P_NOTI"];
     $P_AMT       = $_REQUEST["P_AMT"];
  
   if ($_REQUEST["P_STATUS"] === "00") {             // 인증이 P_STATUS===00 일 경우만 승인 요청
 
        $id_merchant = substr($P_TID,'10','10');     // P_TID 내 MID 구분
        $data = array(
        
         'P_MID' => $id_merchant,         // P_MID
         'P_TID' => $P_TID                // P_TID

        );

        //##########################################################################
		// 승인요청 API url (authUrl) 리스트 는 properties 에 세팅하여 사용합니다.
		// idc_name 으로 수신 받은 센터 네임을 properties 에서 include 하여 승인요청하시면 됩니다.
		//##########################################################################
        $idc_name 	= $_REQUEST["idc_name"];
        $P_REQ_URL  = $prop->getAuthUrl($idc_name); 
 
        if (strcmp($P_REQ_URL, $_REQUEST["P_REQ_URL"]) == 0) {
        
            // curl 통신 시작 
        
            $ch = curl_init();                                                //curl 초기화
            curl_setopt($ch, CURLOPT_URL, $_REQUEST["P_REQ_URL"]);            //URL 지정하기
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                   //요청 결과를 문자열로 반환 
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);                     //connection timeout 10초 
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                      //원격 서버의 인증서가 유효한지 검사 안함
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));    //POST 로 $data 를 보냄
            curl_setopt($ch, CURLOPT_POST, 1);                                //true시 post 전송 
    
    
            $response = curl_exec($ch);
            curl_close($ch);

            parse_str($response, $out);
            //print_r($out);
        }
    }
?>
<!DOCTYPE html>
<html lang="ko">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport"
            content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>KG이니시스 결제샘플</title>
        <link rel="stylesheet" href="css/style.css">
		<link rel="stylesheet" href="css/bootstrap.min.css">
    </head>

    <body class="wrap">

        <!-- 본문 -->
        <main class="col-8 cont" id="bill-01">
            <!-- 페이지타이틀 -->
            <section class="mb-5">
                <div class="tit">
                    <h2>일반결제</h2>
                    <p>KG이니시스 결제창을 호출하여 다양한 지불수단으로 안전한 결제를 제공하는 서비스</p>
                </div>
            </section>
            <!-- //페이지타이틀 -->


            <!-- 카드CONTENTS -->
            <section class="menu_cont mb-5">
                <div class="card">
                    <div class="card_tit">
                        <h3>모바일 일반결제</h3>
                    </div>

                    <!-- 유의사항 -->
                    <div class="card_desc">
                        <h4>※ 유의사항</h4>
                        <ul>
                            <li>테스트MID 결제시 실 승인되며, 당일 자정(24:00) 이전에 자동으로 취소처리 됩니다.</li>
							<li>가상계좌 채번 후 입금할 경우 자동환불되지 않사오니, 가맹점관리자 내 "입금통보테스트" 메뉴를 이용부탁드립니다.<br>(실 입금하신 경우 별도로 환불요청해주셔야 합니다.)</li>
							<li>국민카드 정책상 테스트 결제가 불가하여 오류가 발생될 수 있습니다. 국민, 카카오뱅크 외 다른 카드로 테스트결제 부탁드립니다.</li>
                        </ul>
                    </div>
                    <!-- //유의사항 -->


                    <form name="" id="result" method="post" class="mt-5">
                    <div class="row g-3 justify-content-between" style="--bs-gutter-x:0rem;">
 
                        <?php 
                            if (strcmp($P_REQ_URL, $_REQUEST["P_REQ_URL"]) == -1) {
                                echo "
                                <label class='col-10 col-sm-2 input param' style='border:none;'></label>
                                <label class='col-10 col-sm-9 reinput'> authUrl check Fail (인증까지만 진행됨, 아래 인증 결과) </label>";
                            }
                        ?>

                        <label class="col-10 col-sm-2 gap-2 input param" style="border:none;">P_STATUS</label>
                        <label class="col-10 col-sm-9 reinput">
                            <?php echo @(in_array($out["P_STATUS"] , $out) ? $out["P_STATUS"] : $_REQUEST["P_STATUS"] ) ?>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_RMESG1</label>
                        <label class="col-10 col-sm-9 reinput">
                            <?php echo @(in_array($out["P_RMESG1"] , $out) ? $out["P_RMESG1"] : $_REQUEST["P_RMESG1"] ) ?>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_TID</label>
                        <label class="col-10 col-sm-9 reinput">
                            <?php echo @(in_array($out["P_TID"] , $out) ? $out["P_TID"] : "null" ) ?>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_TYPE</label>
                        <label class="col-10 col-sm-9 reinput">
                            <?php echo @(in_array($out["P_TYPE"] , $out) ? $out["P_TYPE"] : "null" ) ?>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_OID</label>
                        <label class="col-10 col-sm-9 reinput">
                            <?php echo @(in_array($out["P_OID"] , $out) ? $out["P_OID"] : "null" ) ?>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_AMT</label>
                        <label class="col-10 col-sm-9 reinput">
                            <?php echo @(in_array($out["P_AMT"] , $out) ? $out["P_AMT"] : "null" ) ?>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_AUTH_DT</label>
                        <label class="col-10 col-sm-9 reinput">
                            <?php echo @(in_array($out["P_AUTH_DT"] , $out) ? $out["P_AUTH_DT"] : "null" ) ?>
                        </label>
 
                    </div>
                </form>
				
				<button onclick="location.href='INImobile_mo_req.php'" class="btn_solid_pri col-6 mx-auto btn_lg" style="margin-top:50px">돌아가기</button>
					
                </div>
            </section>
			
        </main>
		
    </body>
</html>