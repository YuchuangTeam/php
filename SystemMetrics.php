<?php

class SystemMetrics
{
    public function getCpuUsage()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('wmic cpu get loadpercentage', $cpuOutput);
            $cpuUsage = explode(' ', trim($cpuOutput[1]))[0];
        } elseif (PHP_OS === 'Darwin') {
            exec("top -l 1 | grep 'CPU usage'", $cpuOutput);
            $cpuAble = trim(explode(',', $cpuOutput[0])[2]);
            $cpuUsage = explode(' ', trim($cpuAble))[0];
            $cpuUsage = round(100 - floatval($cpuUsage), 2);
        } else {
            $fp = popen('top -b -n 2 | grep -E "(Cpu)"', "r");//获取某一时刻系统cpu和内存使用情况
            $rs = "";
            while (!feof($fp)) {
                $rs .= fread($fp, 1024);
            }
            pclose($fp);
            $sys_info = explode("\n", $rs);
            $cpu_info = explode(",", $sys_info[1]);  //CPU占有量  数组
            preg_match('/(\d+\.\d+)|(\d+)/', $cpu_info[0], $cpu_usage);
            $cpuUsage = floatval($cpu_usage[0]);

        }
        return $cpuUsage;
    }

    public function getMemoryUsage()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize', $memoryOutput);
            preg_match('/(\d+)\s+(\d+)/', trim($memoryOutput[1]), $memoryInfo);
            $freeMemory = round($memoryInfo[1] / 1024 / 1204, 2);
            $totalMemory = round($memoryInfo[2] / 1024 / 1024, 2);
            $usedMemory = round($totalMemory - $freeMemory, 2);
            $memoryUsage = ['total' => $totalMemory . 'G', 'used' => $usedMemory . 'G', 'usage' => round($usedMemory / $totalMemory * 100, 2)];
        } elseif (PHP_OS === 'Darwin') {
            exec("top -l 1 | grep 'PhysMem'", $memoryOutput);
            $memoryInfo = preg_split('/\s+/', trim($memoryOutput[0]));
            preg_match('/(\d+\w)\sused\s\((\d+\w)\swired/', trim($memoryOutput[0]), $memoryInfo);
            if (count($memoryInfo) > 2) {
                $totalMemory = substr($memoryInfo[1], 0, -1);
                $usedExt = strtoupper(substr($memoryInfo[2], -1));
                $usedMemory = substr($memoryInfo[2], 0, -1);
                if ($usedExt != "G") {
                    $usedMemory = round($usedMemory / 1024, 2);
                }
            } else {
                $totalMemory = 0;
                $usedMemory = 0;
            }
            $memoryUsage = ['total' => $totalMemory . 'G', 'used' => $usedMemory . 'G', 'usage' => round($usedMemory / $totalMemory * 100, 2)];
        } else {
            exec("free | grep Mem", $memoryOutput);
            $memoryInfo = preg_split('/\s+/', trim($memoryOutput[0]));
            $totalMemory = round($memoryInfo[1] / 1024 / 1024, 2);
            $usedMemory = round($memoryInfo[2] / 1024 / 1024, 2);
            $memoryUsage = ['total' => $totalMemory . 'G', 'used' => $usedMemory . 'G', 'usage' => round($usedMemory / $totalMemory * 100, 2)];
        }
        return $memoryUsage;
    }

    public function getDiskUsage()
    {
        $diskUsage = [];
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec('wmic logicaldisk where drivetype=3 get caption,freespace,size', $diskOutput);
            foreach ($diskOutput as $line) {
                $line = trim($line);
                if (preg_match("/^([A-Za-z]):\s+([0-9]+)\s+([0-9]+)/", $line, $matches)) {
                    $drive = $matches[1];
                    $freeSpace = round($matches[2] / 1024 / 1024 / 1024, 2);
                    $totalSize = round($matches[3] / 1024 / 1024 / 1024, 2);
                    $usage = round((($totalSize - $freeSpace) / $totalSize) * 100, 2);
                    $diskUsage[$drive] = ['usage' => $usage, 'total' => $totalSize . "G", 'free' => $freeSpace . "G", 'used' => round($totalSize - $freeSpace, 2) . "G"];
                }
            }
        } elseif (PHP_OS === 'Darwin') {
            exec("df -k | grep '/dev/'", $diskOutput);
            foreach ($diskOutput as $line) {
                $line = preg_split('/\s+/', $line);
                $drive = $line[0];
                $totalSize = round($line[1] / 1024 / 1024, 2);
                $usedSpace = round($line[2] / 1024 / 1024, 2);
                $usage = round(($usedSpace / $totalSize) * 100, 2);
                $diskUsage[$drive] = ['usage' => $usage, 'total' => $totalSize . "G", 'free' => round($totalSize - $usedSpace, 2) . "G", 'used' => $usedSpace . "G"];
            }
        } else {
            exec("df -h | grep '/dev/'", $diskOutput);
            foreach ($diskOutput as $line) {
                $line = preg_split('/\s+/', $line);
                $drive = $line[0];
                $totalSize = $line[1];
                $usedSpace = $line[2];
                $freeSpace = $line[3];
                $usage = str_replace('%', '', $line[4]);
                $diskUsage[$drive] = ['usage' => $usage, 'total' => $totalSize . "G", 'free' => $freeSpace . "G", 'used' => $usedSpace . "G"];
            }
        }
        return $diskUsage;
    }

    public function getUsage()
    {
        $usage = [];
        $usage['cpuUsage'] = $this->getCpuUsage();
        $usage['memoryUsage'] = $this->getMemoryUsage();
        $usage['diskUsage'] = $this->getDiskUsage();
        return $usage;
    }

    public function getOsName()
    {
        $os = shell_exec('cat /etc/redhat-release');
        if ($os) {
            $osName = $os;
        } else {
            $os = strtolower(php_uname('s'));
            if ('darwin' == $os) {
                $version = shell_exec('sw_vers -productVersion');
                $osName = "Mac OS " . trim($version);
            } else if ($os == 'windows nt') {
                preg_match('/\((.*?)\)/', php_uname(), $matches);
                $osVersion = shell_exec('wmic os get Caption /value');
                $osVersion = explode("=", $osVersion)[1];
                $osName = trim(str_replace('Microsoft', '', $osVersion));
                $osName = iconv("GBK", "UTF-8", $osName);
            } else {
                $osName = php_uname('s') . php_uname('v');
            }
        }
        return $osName;
    }
}
