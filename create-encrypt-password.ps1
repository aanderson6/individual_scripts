#CREATE NEW ENCRYPTED PASSWORD

$Key = New-Object Byte[] 32
[Security.Cryptography.RNGCryptoServiceProvider]::Create().GetBytes($Key)
$Key | out-file ".\new-aes.key"
Read-Host "Password to encrypt?" | ConvertTo-SecureString -AsPlainText -Force | ConvertFrom-SecureString -Key $Key | Out-File ".\new-encrypted.txt"

#HOW TO DECRYPT NEW PASSWORD
#$AESKey = Get-Content -Path ".\new-aes.key"
#$encrypted_ss = Get-Content ".\new-encrypted.txt" | ConvertTo-SecureString -Key $AESKey
#$encrypted_bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($encrypted_ss)
#$encrypted_decrypted = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($encrypted_bstr)