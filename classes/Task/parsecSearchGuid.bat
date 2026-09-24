@echo off
chcp 1251 > nul
c:\xampp\php\php.exe c:\xampp\htdocs\city\modules\minion\minion --task=parsecSearchGuid --guid=%1 %2 %3 %4