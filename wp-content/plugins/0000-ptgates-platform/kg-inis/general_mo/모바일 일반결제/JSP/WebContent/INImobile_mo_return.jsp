<%@page language="java" contentType="text/html; charset=UTF-8" pageEncoding="UTF-8"%>
<%@page import="java.util.*"%>
<%@page import="java.nio.charset.StandardCharsets"%>
<%@page import="java.net.URL"%>
<%@page import="java.net.HttpURLConnection"%>
<%@page import="java.io.*"%>



<%

	/////////////////////////////////////////////////////////////////////////////
	///// 1. 변수 초기화 및 POST 인증값 받음                                 ////
	/////////////////////////////////////////////////////////////////////////////
	request.setCharacterEncoding("UTF-8");

	String P_STATUS = request.getParameter("P_STATUS");			// 인증 상태
	String P_RMESG1 = request.getParameter("P_RMESG1");			// 인증 결과 메시지
	String P_TID = request.getParameter("P_TID");				// 인증 거래번호
	String P_REQ_URL = request.getParameter("P_REQ_URL");		// 결제요청 URL
	String P_NOTI = request.getParameter("P_NOTI");				// 기타주문정보
	
	HashMap<String, String> map = new HashMap<String, String>();

	
	////////////////////////////////////////////////////////////////////////////
	// 인증성공 P_STATUS=00 확인
	// IDC센터 확인 [idc_name=fc,ks,stg]	
	// idc_name 으로 수신 받은 값 기준 properties 에 설정된 승인URL과 P_REQ_URL 이 같은지 비교
	// 승인URL은  https://manual.inicis.com 참조
	////////////////////////////////////////////////////////////////////////////
	
	System.out.println (P_REQ_URL);

	if(P_STATUS.equals("00") && P_REQ_URL.equals(ResourceBundle.getBundle("properties/idc_name").getString(request.getParameter("idc_name")))) {
		
		/////////////////////////////////////////////////////////////////////////////
		///// 2. 상점 아이디 설정 :                                              ////
		/////    결제요청 페이지에서 사용한 MID값과 동일하게 세팅해야 함...      ////
		/////////////////////////////////////////////////////////////////////////////
		
		String P_MID = P_TID.substring(10, 20);
	
		/////////////////////////////////////////////////////////////////////////////
		//// 3. 승인요청 :                                                      ////
		//// 	승인요청 API url (P_REQ_URL) 리스트 는 properties 에 세팅하여 사용합니다.
		//// 	idc_name 으로 수신 받은 센터 네임을 properties 에서 확인하여 승인요청하시면 됩니다.
		/////////////////////////////////////////////////////////////////////////////
		
		P_REQ_URL = P_REQ_URL + "?P_TID=" + P_TID + "&P_MID=" + P_MID;
	
		try {
			URL reqUrl = new URL(P_REQ_URL);
			HttpURLConnection conn = (HttpURLConnection) reqUrl.openConnection();
			
			if (conn != null) {
				conn.setRequestMethod("POST");
				conn.setDefaultUseCaches(false);
				conn.setDoOutput(true);
				
				if (conn.getDoOutput()) {
					conn.getOutputStream().flush();
					conn.getOutputStream().close();
				}

				conn.connect();
				
				BufferedReader br = new BufferedReader(new InputStreamReader(conn.getInputStream(), StandardCharsets.UTF_8));
					
				String[] values = new String(br.readLine()).split("&"); 
				for( int x = 0; x < values.length; x++ ) {
						  
					// 승인결과를 파싱값 잘라 hashmap에 저장
					int i = values[x].indexOf("=");
					String key1 = values[x].substring(0, i);
					String value1 = values[x].substring(i+1);
					map.put(key1, value1);
					  
				}
					
				br.close();
			}

		}catch(Exception e ) {
			e.printStackTrace();
		}  
	    
	} else {
		
		map.put("P_STATUS", P_STATUS);
		map.put("P_RMESG1", P_RMESG1);

	}
%>

<!DOCTYPE html>
<html lang="ko">
<head>
	<meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
 
                        <label class="col-10 col-sm-2 gap-2 input param" style="border:none;">P_STATUS</label>
                        <label class="col-10 col-sm-9 reinput">
                            <%=map.get("P_STATUS")  %>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_RMESG1</label>
                        <label class="col-10 col-sm-9 reinput">
                            <%=map.get("P_RMESG1")  %>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_TID</label>
                        <label class="col-10 col-sm-9 reinput">
                            <%=map.get("P_TID")  %>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_TYPE</label>
                        <label class="col-10 col-sm-9 reinput">
                            <%=map.get("P_TYPE")  %>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_OID</label>
                        <label class="col-10 col-sm-9 reinput">
                            <%=map.get("P_OID")  %>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_AMT</label>
                        <label class="col-10 col-sm-9 reinput">
                            <%=map.get("P_AMT")  %>
                        </label>
						
						<label class="col-10 col-sm-2 input param" style="border:none;">P_AUTH_DT</label>
                        <label class="col-10 col-sm-9 reinput">
                            <%=map.get("P_AUTH_DT")  %>
                        </label>
 
                    </div>
                </form>
				
				<button onclick="location.href='INImobile_mo_req.jsp'" class="btn_solid_pri col-6 mx-auto btn_lg" style="margin-top:50px">돌아가기</button>
					
                </div>
            </section>
			
        </main>
		
    </body>
</html>