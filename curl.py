import urllib.request
import re
import urllib.error

def fetch_and_find_endpoints(url):
    """
    Mengambil konten dari URL dan mencari potensi endpoint (link).
    """
    print(f"[*] Mengambil data dari: {url}")
    
    # Menambahkan header User-Agent agar tidak diblokir oleh server
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    }
    
    req = urllib.request.Request(url, headers=headers)
    
    try:
        # Melakukan request ke URL
        with urllib.request.urlopen(req) as response:
            html_content = response.read().decode('utf-8')
            
            print("[+] Berhasil mengambil halaman HTML.")
            print("[*] Mencari potensi endpoint (href/src/action)...\n")
            
            # Menggunakan Regular Expression untuk mencari atribut href, src, atau action
            # Pola ini mencari string di dalam tanda kutip setelah href=, src=, atau action=
            pattern = re.compile(r'(?:href|src|action)\s*=\s*["\']([^"\']+)["\']', re.IGNORECASE)
            
            endpoints = pattern.findall(html_content)
            
            # Menghapus duplikat dan mengurutkan
            unique_endpoints = sorted(list(set(endpoints)))
            
            if unique_endpoints:
                print(f"Ditemukan {len(unique_endpoints)} potensi endpoint/link:\n")
                for endpoint in unique_endpoints:
                    # Filter sederhana: abaikan yang kosong atau hanya hash (#)
                    if endpoint and endpoint != '#':
                        print(f" - {endpoint}")
            else:
                print("[-] Tidak ada endpoint yang ditemukan.")
                
    except urllib.error.URLError as e:
        print(f"[-] Gagal mengambil data. Error: {e.reason}")
    except Exception as e:
        print(f"[-] Terjadi kesalahan tak terduga: {e}")

if __name__ == "__main__":
    target_url = "https://sumbarprov.go.id"
    fetch_and_find_endpoints(target_url)