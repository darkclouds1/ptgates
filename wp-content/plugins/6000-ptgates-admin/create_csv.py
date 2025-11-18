import pandas as pd
import re

# 파일 읽기
file_path = "/mnt/data/2024년도 제52회 물리치료사 국가시험 해설.txt"
with open(file_path, "r", encoding="utf-8") as f:
    text = f.read()

# 정규식으로 문제별로 분리
pattern = re.compile(
    r"(\d+)\.\s*(.*?)\n정답[:：]\s*([^\n]+)\n해설\s*(.*?)\n분류[:：]\s*(.*?)(?=\n\d+\.|\Z)",
    re.S
)
matches = pattern.findall(text)

# 데이터프레임 생성
df = pd.DataFrame(matches, columns=["문제 번호", "문제", "정답", "해설", "분류"])

# '교시'는 번호 기준으로 1~85는 1교시, 86~는 2교시로 분류
df["교시"] = df["문제 번호"].astype(int).apply(lambda x: "1교시" if x <= 85 else "2교시")

# 컬럼 순서 조정
df = df[["문제 번호", "교시", "문제", "정답", "해설", "분류"]]

# CSV 파일로 저장
csv_path = "/mnt/data/2024년도_제52회_물리치료사_국가시험_해설.csv"
df.to_csv(csv_path, index=False, encoding="utf-8-sig")

csv_path
