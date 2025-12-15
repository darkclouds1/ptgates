<?php
require_once('libs/INIStdPayUtil.php');
$SignatureUtil = new INIStdPayUtil();

$P_MID 			= "INIpayTest";  							    // 상점아이디			
$P_AMT 			= "1000"; 									    // 결제 금액
$P_OID 			= $P_MID . "_" .$SignatureUtil->getTimestamp(); // 가맹점 주문번호(가맹점에서 직접 설정)
$P_TIMESTAMP	= $SignatureUtil->getTimestamp(); 				// util에 의해서 자동생성
$HashKey 		= "3CB8183A4BE283555ACC8363C0360223";   		// 모바일 금액위변조 Hash Key

$data = $P_AMT . $P_OID . $P_TIMESTAMP . $HashKey;

$digest = hash('sha512', $data, true); 
$P_CHKFAKE = base64_encode($digest);

echo $P_CHKFAKE;
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
		
		<script> 
	        function on_pay() { 
	        	myform = document.mobileweb; 
	        	myform.action = "https://mobile.inicis.com/smart/payment/";
	        	myform.target = "_self";
	        	myform.submit(); 
	        }
        </script> 
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


                    <form name="mobileweb" id="" method="post" class="mt-5" accept-charset="euc-kr">
                        <div class="row g-3 justify-content-between" style="--bs-gutter-x:0rem;">
				    
                            <label class="col-10 col-sm-2 input param" style="border:none;">P_INI_PAYMENT</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_INI_PAYMENT" value="CARD">
                            </label>
				    		
				    		<label class="col-10 col-sm-2 input param" style="border:none;">P_MID</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_MID" value="<?=$P_MID?>">
                            </label>
				    
                            <label class="col-10 col-sm-2 input param" style="border:none;">P_OID</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_OID" value="<?=$P_OID?>">
                            </label>
				    		
				    		<label class="col-10 col-sm-2 input param" style="border:none;">P_AMT</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_AMT" value="<?=$P_AMT?>">
                            </label>
				    		
				    		<label class="col-10 col-sm-2 input param" style="border:none;">P_GOODS</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_GOODS" value="테스트상품">
                            </label>
				    		
				    		<label class="col-10 col-sm-2 input param" style="border:none;">P_UNAME</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_UNAME" value="테스터">
                            </label>
				    		
				    		<label class="col-10 col-sm-2 input param" style="border:none;">P_MOBILE</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_MOBILE" value="01012345678">
                            </label>
				    		
				    		<label class="col-10 col-sm-2 input param" style="border:none;">P_EMAIL</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_EMAIL" value="test@test.com">
                            </label>
				    		
				    		<input type="hidden" name="P_NEXT_URL" value="https://[가맹점도메인]/INImobile_mo_return.php">
							
                            <input type="hidden" name="P_CHARSET" value="utf8">
							
							<input type="hidden" name="P_TIMESTAMP" value="<?=$P_TIMESTAMP?>">
                            <input type="hidden" name="P_CHKFAKE" value="<?=$P_CHKFAKE?>">
                            
				    		<label class="col-10 col-sm-2 input param" style="border:none;">P_RESERVED</label>
                            <label class="col-10 col-sm-9 input">
                                <input type="text" name="P_RESERVED" value="below1000=Y&vbank_receipt=Y&centerCd=Y&amt_hash=Y">
                            </label>
							
                        </div>
                    </form>
				
				    <button onclick="on_pay()" class="btn_solid_pri col-6 mx-auto btn_lg" style="margin-top:50px">결제 요청</button>
					
                </div>
            </section>
			
        </main>
		
    </body>
</html>