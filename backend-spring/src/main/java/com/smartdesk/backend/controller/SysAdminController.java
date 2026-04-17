package com.smartdesk.backend.controller;

import com.smartdesk.backend.service.SysAdminService;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/sysadmin")
public class SysAdminController {

    private final SysAdminService sysAdminService;

    public SysAdminController(SysAdminService sysAdminService) {
        this.sysAdminService = sysAdminService;
    }

    @PostMapping("/moderate/{userId}")
    public void moderateUser(@PathVariable Long userId, @RequestParam String action) {
        if ("BAN".equalsIgnoreCase(action)) {
            sysAdminService.banUser(userId);
        }
    }

    @GetMapping("/logs")
    public List<String> getSystemLogs() {
        return sysAdminService.getSystemLogs();
    }
}
